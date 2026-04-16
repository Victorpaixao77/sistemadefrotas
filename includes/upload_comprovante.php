<?php
/**
 * Validação de upload de comprovantes (PDF / imagens).
 */

if (!function_exists('sf_save_comprovante_upload')) {
    /** Extensões permitidas (sem ponto). */
    function sf_comprovante_allowed_extensions(): array
    {
        return ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    }

    /** Tamanho máximo em bytes (5 MiB). */
    function sf_comprovante_max_bytes(): int
    {
        return 5 * 1024 * 1024;
    }

    /**
     * @param array $file elemento $_FILES['campo']
     * @param string $uploadDirAbs caminho absoluto no disco
     * @param string $webPrefix prefixo na URL (ex.: uploads/comprovantes ou uploads/recibos)
     * @param bool $basenameOnly se true, retorna só o nome do arquivo (compatível com colunas que guardam só o file)
     * @return string caminho web ou basename
     * @throws Exception
     */
    function sf_save_comprovante_upload(array $file, string $uploadDirAbs, string $webPrefix = 'uploads/comprovantes', bool $basenameOnly = false): string
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload inválido ou incompleto.');
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Arquivo de upload inválido.');
        }
        if (!empty($file['size']) && (int) $file['size'] > sf_comprovante_max_bytes()) {
            throw new Exception('Arquivo excede o tamanho máximo permitido (5 MB).');
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, sf_comprovante_allowed_extensions(), true)) {
            throw new Exception('Tipo de arquivo não permitido. Use PDF, JPG, PNG ou WEBP.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        $allowedMime = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
        ];
        if (!in_array($mime, $allowedMime, true)) {
            throw new Exception('Conteúdo do arquivo não corresponde a um tipo permitido.');
        }
        $uploadDirAbs = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $uploadDirAbs), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDirAbs) && !mkdir($uploadDirAbs, 0755, true)) {
            throw new Exception('Não foi possível criar a pasta de uploads.');
        }
        $safeName = uniqid('comprovante_', true) . '.' . $ext;
        $dest = $uploadDirAbs . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new Exception('Erro ao salvar o arquivo.');
        }
        if ($basenameOnly) {
            return $safeName;
        }
        $rel = rtrim(str_replace('\\', '/', $webPrefix), '/') . '/' . $safeName;
        return $rel;
    }
}
