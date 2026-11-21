<?php
declare(strict_types=1);

require_once __DIR__ . '/adm_deploy_crypto.php';

/**
 * Execução SSH / local para deploy.
 * Requer cliente OpenSSH no servidor que roda o Control Center.
 */
function adm_deploy_ssh_available(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $out = PHP_OS_FAMILY === 'Windows'
        ? @shell_exec('where ssh 2>nul')
        : @shell_exec('which ssh 2>/dev/null');
    $ok = is_string($out) && trim($out) !== '';
    return $ok;
}

function adm_deploy_plink_available(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $out = PHP_OS_FAMILY === 'Windows'
        ? @shell_exec('where plink 2>nul')
        : @shell_exec('which plink 2>/dev/null');
    $ok = is_string($out) && trim($out) !== '';
    return $ok;
}

/** @return array{ok:bool, stdout:string, stderr:string, code:int} */
function adm_deploy_ssh_exec(array $server, string $remoteCommand, int $timeoutSec = 120): array
{
    $host = trim((string) ($server['host'] ?? ''));
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1') {
        return adm_deploy_local_exec($server, $remoteCommand, $timeoutSec);
    }
    if (!adm_deploy_ssh_available()) {
        return ['ok' => false, 'stdout' => '', 'stderr' => 'Cliente SSH não encontrado no servidor do Control Center.', 'code' => 127];
    }

    $port = max(1, min(65535, (int) ($server['porta_ssh'] ?? 22)));
    $user = trim((string) ($server['usuario'] ?? ''));
    $auth = (string) ($server['auth_method'] ?? 'ssh_key');
    $target = $user . '@' . $host;

    if ($auth === 'password') {
        $pass = adm_deploy_decrypt($server['password_enc'] ?? null);
        if (!$pass) {
            return ['ok' => false, 'stdout' => '', 'stderr' => 'Senha SSH não configurada. Salve a senha novamente no formulário do servidor.', 'code' => 1];
        }
        $viaPlink = adm_deploy_ssh_exec_plink($host, $port, $user, $pass, $remoteCommand, $timeoutSec);
        if ($viaPlink['ok']) {
            return $viaPlink;
        }
        $viaAsk = adm_deploy_ssh_exec_askpass($host, $port, $user, $pass, $remoteCommand, $timeoutSec);
        if ($viaAsk['ok']) {
            return $viaAsk;
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            $viaPass = adm_deploy_ssh_exec_sshpass($host, $port, $user, $pass, $remoteCommand, $timeoutSec);
            if ($viaPass['ok']) {
                return $viaPass;
            }
            return adm_deploy_ssh_pick_best_failure([$viaPlink, $viaAsk, $viaPass]);
        }
        return adm_deploy_ssh_pick_best_failure([$viaPlink, $viaAsk]);
    }

    $args = ['-o', 'BatchMode=yes', '-o', 'ConnectTimeout=15', '-o', 'StrictHostKeyChecking=accept-new', '-p', (string) $port];
    $tmpKey = null;
    $key = adm_deploy_decrypt($server['ssh_key_enc'] ?? null);
    if ($key) {
        $tmpKey = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_deploy_key_' . bin2hex(random_bytes(8));
        file_put_contents($tmpKey, $key);
        @chmod($tmpKey, 0600);
        $args[] = '-i';
        $args[] = $tmpKey;
    } else {
        return ['ok' => false, 'stdout' => '', 'stderr' => 'Chave SSH não configurada. Cole a chave privada ou use autenticação por senha.', 'code' => 1];
    }

    $cmd = array_merge(['ssh'], $args, [$target, $remoteCommand]);
    $result = adm_deploy_proc_run($cmd, $timeoutSec);

    if ($tmpKey && is_file($tmpKey)) {
        @unlink($tmpKey);
    }

    return $result;
}

/** @param array<int, array{ok:bool, stdout:string, stderr:string, code:int}> $attempts */
function adm_deploy_ssh_pick_best_failure(array $attempts): array
{
    foreach ($attempts as $a) {
        if (($a['stderr'] ?? '') !== '') {
            return $a;
        }
    }
    return $attempts[0] ?? ['ok' => false, 'stdout' => '', 'stderr' => 'Falha ao conectar via SSH.', 'code' => 1];
}

/** @return array{ok:bool, stdout:string, stderr:string, code:int} */
function adm_deploy_ssh_exec_plink(string $host, int $port, string $user, string $pass, string $remoteCommand, int $timeoutSec): array
{
    if (!adm_deploy_plink_available()) {
        return ['ok' => false, 'stdout' => '', 'stderr' => '', 'code' => 127];
    }
    $remote = str_replace('"', '\"', $remoteCommand);
    $cmd = sprintf(
        'plink -ssh -batch -P %d -l %s -pw %s %s "%s"',
        $port,
        escapeshellarg($user),
        escapeshellarg($pass),
        escapeshellarg($host),
        $remote
    );
    $result = adm_deploy_shell_run($cmd, $timeoutSec);
    if ($result['ok']) {
        return $result;
    }
    if (stripos($result['stderr'], 'host key') !== false || stripos($result['stderr'], 'Cannot confirm') !== false) {
        $cmd2 = sprintf(
            'echo y | plink -ssh -P %d -l %s -pw %s %s "%s"',
            $port,
            escapeshellarg($user),
            escapeshellarg($pass),
            escapeshellarg($host),
            $remote
        );
        return adm_deploy_shell_run($cmd2, $timeoutSec);
    }
    return $result;
}

/** @return array{ok:bool, stdout:string, stderr:string, code:int} */
function adm_deploy_ssh_exec_sshpass(string $host, int $port, string $user, string $pass, string $remoteCommand, int $timeoutSec): array
{
    $which = @shell_exec('which sshpass 2>/dev/null');
    if (!is_string($which) || trim($which) === '') {
        return ['ok' => false, 'stdout' => '', 'stderr' => '', 'code' => 127];
    }
    $cmd = sprintf(
        'sshpass -p %s ssh -o StrictHostKeyChecking=accept-new -o ConnectTimeout=15 -o PreferredAuthentications=password -o PubkeyAuthentication=no -p %d %s@%s %s',
        escapeshellarg($pass),
        $port,
        escapeshellarg($user),
        escapeshellarg($host),
        escapeshellarg($remoteCommand)
    );
    return adm_deploy_shell_run($cmd, $timeoutSec);
}

/** @return array{ok:bool, stdout:string, stderr:string, code:int} */
function adm_deploy_ssh_exec_askpass(string $host, int $port, string $user, string $pass, string $remoteCommand, int $timeoutSec): array
{
    $askpass = adm_deploy_ssh_create_askpass_script();
    if ($askpass === null) {
        return ['ok' => false, 'stdout' => '', 'stderr' => 'Não foi possível criar script SSH_ASKPASS.', 'code' => 1];
    }

    $env = [
        'SF_DEPLOY_SSH_PASS' => $pass,
        'SSH_ASKPASS' => $askpass,
        'SSH_ASKPASS_REQUIRE' => 'force',
        'DISPLAY' => ':0',
        'SSH_ASKPASS_DISABLE_WINDOW' => '1',
    ];
    if (PHP_OS_FAMILY === 'Windows') {
        $env['TERM'] = 'dumb';
    }

    $args = [
        'ssh',
        '-o', 'ConnectTimeout=15',
        '-o', 'StrictHostKeyChecking=accept-new',
        '-o', 'PreferredAuthentications=password,keyboard-interactive',
        '-o', 'PubkeyAuthentication=no',
        '-o', 'NumberOfPasswordPrompts=1',
        '-p', (string) $port,
        $user . '@' . $host,
        $remoteCommand,
    ];

    $result = adm_deploy_proc_run($args, $timeoutSec, false, $env);
    @unlink($askpass);
    return $result;
}

function adm_deploy_ssh_create_askpass_script(): ?string
{
    $dir = sys_get_temp_dir();
    $id = bin2hex(random_bytes(8));
    if (PHP_OS_FAMILY === 'Windows') {
        $path = $dir . DIRECTORY_SEPARATOR . 'sf_deploy_askpass_' . $id . '.cmd';
        $content = "@echo off\r\necho %SF_DEPLOY_SSH_PASS%\r\n";
    } else {
        $path = $dir . DIRECTORY_SEPARATOR . 'sf_deploy_askpass_' . $id . '.sh';
        $content = "#!/bin/sh\necho \"$SF_DEPLOY_SSH_PASS\"\n";
    }
    if (@file_put_contents($path, $content) === false) {
        return null;
    }
    if (PHP_OS_FAMILY !== 'Windows') {
        @chmod($path, 0700);
    }
    return $path;
}

/** Executa no caminho local quando host é localhost (simula produção local). */
function adm_deploy_local_exec(array $server, string $command, int $timeoutSec = 120): array
{
    $path = rtrim((string) ($server['project_path'] ?? ''), '/\\');
    if ($path === '' || !is_dir($path)) {
        return ['ok' => false, 'stdout' => '', 'stderr' => 'Caminho do projeto inválido: ' . $path, 'code' => 1];
    }
    $wrapped = 'cd ' . escapeshellarg($path) . ' && ' . $command;
    return adm_deploy_shell_run($wrapped, $timeoutSec);
}

/** @return array{ok:bool, stdout:string, stderr:string, code:int} */
function adm_deploy_shell_run(string $command, int $timeoutSec = 120): array
{
    if (PHP_OS_FAMILY === 'Windows') {
        $command = 'cmd /C ' . $command;
    }
    return adm_deploy_proc_run($command, $timeoutSec, true);
}

/** @param array<int,string>|string $cmd */
function adm_deploy_proc_run(array|string $cmd, int $timeoutSec = 120, bool $shell = false, ?array $env = null): array
{
    $descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $baseEnv = getenv();
    if (!is_array($baseEnv)) {
        $baseEnv = [];
    }
    if ($env !== null) {
        $baseEnv = array_merge($baseEnv, $env);
    }
    $proc = @proc_open($cmd, $descriptor, $pipes, null, $baseEnv, ['bypass_shell' => !$shell]);
    if (!is_resource($proc)) {
        return ['ok' => false, 'stdout' => '', 'stderr' => 'Falha ao iniciar processo.', 'code' => 1];
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $start = time();
    while (true) {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $status = proc_get_status($proc);
        if (!$status['running']) {
            break;
        }
        if (time() - $start > $timeoutSec) {
            proc_terminate($proc);
            $stderr .= "\n[timeout {$timeoutSec}s]";
            break;
        }
        usleep(100000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    return ['ok' => $code === 0, 'stdout' => trim($stdout), 'stderr' => trim($stderr), 'code' => $code];
}

/** SCP de arquivo remoto → local */
function adm_deploy_scp_download(array $server, string $remotePath, string $localFile): array
{
    $host = trim((string) ($server['host'] ?? ''));
    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '') {
        $ok = @copy($remotePath, $localFile);
        return ['ok' => $ok, 'stdout' => $ok ? 'copied' : '', 'stderr' => $ok ? '' : 'copy failed', 'code' => $ok ? 0 : 1];
    }
    if (!adm_deploy_ssh_available()) {
        return ['ok' => false, 'stdout' => '', 'stderr' => 'SCP indisponível', 'code' => 127];
    }

    $port = max(1, min(65535, (int) ($server['porta_ssh'] ?? 22)));
    $user = trim((string) ($server['usuario'] ?? ''));
    $auth = (string) ($server['auth_method'] ?? 'ssh_key');
    $target = $user . '@' . $host . ':' . $remotePath;
    $localDir = dirname($localFile);
    if (!is_dir($localDir)) {
        @mkdir($localDir, 0755, true);
    }

    if ($auth === 'password') {
        $pass = adm_deploy_decrypt($server['password_enc'] ?? null);
        if (!$pass) {
            return ['ok' => false, 'stdout' => '', 'stderr' => 'Senha SSH não configurada.', 'code' => 1];
        }
        if (adm_deploy_plink_available()) {
            $cmd = sprintf(
                'pscp -batch -P %d -l %s -pw %s %s %s',
                $port,
                escapeshellarg($user),
                escapeshellarg($pass),
                escapeshellarg($target),
                escapeshellarg($localFile)
            );
            $r = adm_deploy_shell_run($cmd, 300);
            if ($r['ok']) {
                return $r;
            }
            if (stripos($r['stderr'], 'host key') !== false) {
                $cmd2 = sprintf(
                    'echo y | pscp -P %d -l %s -pw %s %s %s',
                    $port,
                    escapeshellarg($user),
                    escapeshellarg($pass),
                    escapeshellarg($target),
                    escapeshellarg($localFile)
                );
                return adm_deploy_shell_run($cmd2, 300);
            }
            return $r;
        }
    }

    $args = ['-P', (string) $port, '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new'];
    $tmpKey = null;
    $key = adm_deploy_decrypt($server['ssh_key_enc'] ?? null);
    if ($key) {
        $tmpKey = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_deploy_key_' . bin2hex(random_bytes(8));
        file_put_contents($tmpKey, $key);
        @chmod($tmpKey, 0600);
        $args[] = '-i';
        $args[] = $tmpKey;
    }
    $cmd = array_merge(['scp'], $args, [$target, $localFile]);
    $result = adm_deploy_proc_run($cmd, 300);
    if ($tmpKey && is_file($tmpKey)) {
        @unlink($tmpKey);
    }
    return $result;
}

/** SCP de arquivo local → remoto */
function adm_deploy_scp_upload(array $server, string $localFile, string $remotePath): array
{
    $host = trim((string) ($server['host'] ?? ''));
    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '') {
        $dest = rtrim((string) $server['project_path'], '/\\') . '/' . ltrim(str_replace('\\', '/', $remotePath), '/');
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ok = @copy($localFile, $dest);
        return ['ok' => $ok, 'stdout' => $ok ? 'copied' : '', 'stderr' => $ok ? '' : 'copy failed', 'code' => $ok ? 0 : 1];
    }
    if (!adm_deploy_ssh_available()) {
        return ['ok' => false, 'stdout' => '', 'stderr' => 'SCP indisponível', 'code' => 127];
    }

    $port = max(1, min(65535, (int) ($server['porta_ssh'] ?? 22)));
    $user = trim((string) ($server['usuario'] ?? ''));
    $auth = (string) ($server['auth_method'] ?? 'ssh_key');
    $target = $user . '@' . $host . ':' . $remotePath;

    if ($auth === 'password') {
        $pass = adm_deploy_decrypt($server['password_enc'] ?? null);
        if (!$pass) {
            return ['ok' => false, 'stdout' => '', 'stderr' => 'Senha SSH não configurada.', 'code' => 1];
        }
        if (adm_deploy_plink_available()) {
            $cmd = sprintf(
                'pscp -batch -P %d -l %s -pw %s %s %s',
                $port,
                escapeshellarg($user),
                escapeshellarg($pass),
                escapeshellarg($localFile),
                escapeshellarg($target)
            );
            $r = adm_deploy_shell_run($cmd, 300);
            if ($r['ok']) {
                return $r;
            }
            if (stripos($r['stderr'], 'host key') !== false) {
                $cmd2 = sprintf(
                    'echo y | pscp -P %d -l %s -pw %s %s %s',
                    $port,
                    escapeshellarg($user),
                    escapeshellarg($pass),
                    escapeshellarg($localFile),
                    escapeshellarg($target)
                );
                return adm_deploy_shell_run($cmd2, 300);
            }
            return $r;
        }
        $askpass = adm_deploy_ssh_create_askpass_script();
        if ($askpass === null) {
            return ['ok' => false, 'stdout' => '', 'stderr' => 'Não foi possível criar SSH_ASKPASS para SCP.', 'code' => 1];
        }
        $env = [
            'SF_DEPLOY_SSH_PASS' => $pass,
            'SSH_ASKPASS' => $askpass,
            'SSH_ASKPASS_REQUIRE' => 'force',
            'DISPLAY' => ':0',
            'SSH_ASKPASS_DISABLE_WINDOW' => '1',
        ];
        $cmd = [
            'scp',
            '-P', (string) $port,
            '-o', 'StrictHostKeyChecking=accept-new',
            '-o', 'PreferredAuthentications=password,keyboard-interactive',
            '-o', 'PubkeyAuthentication=no',
            $localFile,
            $target,
        ];
        $r = adm_deploy_proc_run($cmd, 300, false, $env);
        @unlink($askpass);
        return $r;
    }

    $args = ['-P', (string) $port, '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new'];
    $tmpKey = null;
    $key = adm_deploy_decrypt($server['ssh_key_enc'] ?? null);
    if ($key) {
        $tmpKey = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sf_deploy_key_' . bin2hex(random_bytes(8));
        file_put_contents($tmpKey, $key);
        @chmod($tmpKey, 0600);
        $args[] = '-i';
        $args[] = $tmpKey;
    }
    $cmd = array_merge(['scp'], $args, [$localFile, $target]);
    $result = adm_deploy_proc_run($cmd, 300);
    if ($tmpKey && is_file($tmpKey)) {
        @unlink($tmpKey);
    }
    return $result;
}

function adm_deploy_homolog_root(array $server): string
{
    $p = trim((string) ($server['homolog_path'] ?? ''));
    if ($p !== '' && is_dir($p)) {
        return rtrim($p, '/\\');
    }
    return dirname(__DIR__, 2);
}

function adm_deploy_is_local_server(array $server): bool
{
    $h = strtolower(trim((string) ($server['host'] ?? '')));
    return in_array($h, ['', 'localhost', '127.0.0.1', '::1'], true);
}

/** Quote seguro para shell POSIX (comando executado no servidor remoto Linux). */
function adm_deploy_sh_quote(string $s): string
{
    return "'" . str_replace("'", "'\\''", $s) . "'";
}
