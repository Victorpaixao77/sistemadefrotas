<?php
/**
 * Script de Reversão para Desenvolvimento
 * Sistema de Gestão de Frotas
 * 
 * Este script reverte as configurações para desenvolvimento
 * Use apenas se precisar voltar ao ambiente de desenvolvimento
 */

echo "=== REVERSÃO PARA DESENVOLVIMENTO ===\n";
echo "Sistema de Gestão de Frotas\n\n";

echo "⚠️  ATENÇÃO: Este script irá reverter todas as configurações para desenvolvimento.\n";
echo "Isso irá sobrescrever as configurações de produção!\n\n";

echo "Deseja continuar? (s/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) !== 's') {
    echo "Operação cancelada.\n";
    exit;
}

echo "\n=== PROCURANDO BACKUPS ===\n";

// Procurar arquivos de backup
$backup_files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator('.'),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && preg_match('/\.backup\.\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}$/', $file->getPathname())) {
        $backup_files[] = $file->getPathname();
    }
}

if (empty($backup_files)) {
    echo "❌ Nenhum arquivo de backup encontrado.\n";
    echo "Não é possível reverter as configurações.\n";
    exit(1);
}

echo "Encontrados " . count($backup_files) . " arquivos de backup:\n";
foreach ($backup_files as $backup) {
    echo "- $backup\n";
}

echo "\n=== REVERTENDO CONFIGURAÇÕES ===\n";

$sucessos = 0;
$erros = 0;

foreach ($backup_files as $backup_file) {
    // Extrair nome do arquivo original (remover extensão de backup)
    $original_file = preg_replace('/\.backup\.\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}$/', '', $backup_file);
    
    echo "📝 Revertendo: $original_file\n";
    
    if (file_exists($original_file)) {
        // Fazer backup do arquivo atual antes de reverter
        $current_backup = $original_file . '.current.' . date('Y-m-d-H-i-s');
        if (copy($original_file, $current_backup)) {
            echo "   💾 Backup do arquivo atual: $current_backup\n";
        }
    }
    
    // Restaurar arquivo original
    if (copy($backup_file, $original_file)) {
        echo "   ✅ Revertido com sucesso\n";
        $sucessos++;
    } else {
        echo "   ❌ Erro ao reverter\n";
        $erros++;
    }
}

echo "\n=== RESUMO ===\n";
echo "✅ Arquivos revertidos: $sucessos\n";
echo "❌ Erros: $erros\n";

if ($erros == 0) {
    echo "\n🎉 Reversão concluída com sucesso!\n";
    echo "O sistema está configurado para desenvolvimento.\n";
} else {
    echo "\n⚠️  Alguns arquivos não puderam ser revertidos.\n";
    echo "Verifique as permissões e tente novamente.\n";
}

echo "\n=== PRÓXIMOS PASSOS ===\n";
echo "1. Teste a conexão com o banco de desenvolvimento\n";
echo "2. Verifique se todas as funcionalidades estão funcionando\n";
echo "3. Se necessário, restaure o banco de dados de desenvolvimento\n";

?> 