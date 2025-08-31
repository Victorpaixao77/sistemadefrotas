#Requires -Version 5.0
#Requires -RunAsAdministrator

<#
.SYNOPSIS
    Script para instalar OpenSSL automaticamente no Windows
.DESCRIPTION
    Este script verifica se o OpenSSL está instalado e, se não estiver,
    instala automaticamente usando Chocolatey ou download direto.
.AUTHOR
    Sistema de Frotas
.VERSION
    1.0
#>

# Configurações
$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

# Cores para output
$Colors = @{
    Success = "Green"
    Error = "Red"
    Warning = "Yellow"
    Info = "Cyan"
    Default = "White"
}

function Write-ColorOutput {
    param(
        [string]$Message,
        [string]$Color = "Default"
    )
    Write-Host $Message -ForegroundColor $Colors[$Color]
}

function Test-OpenSSL {
    try {
        $null = Get-Command openssl -ErrorAction Stop
        $version = & openssl version 2>$null
        if ($version) {
            Write-ColorOutput "✅ OpenSSL já está instalado: $version" "Success"
            return $true
        }
    }
    catch {
        Write-ColorOutput "❌ OpenSSL não encontrado no sistema" "Warning"
        return $false
    }
    return $false
}

function Install-Chocolatey {
    Write-ColorOutput "🍫 Instalando Chocolatey..." "Info"
    
    try {
        # Baixar e executar script de instalação do Chocolatey
        $chocoScript = "https://community.chocolatey.org/install.ps1"
        Write-ColorOutput "📥 Baixando script de instalação..." "Info"
        
        $webClient = New-Object System.Net.WebClient
        $webClient.Headers.Add("User-Agent", "PowerShell")
        $installScript = $webClient.DownloadString($chocoScript)
        
        Write-ColorOutput "🔧 Executando instalação..." "Info"
        Invoke-Expression $installScript
        
        # Verificar se foi instalado
        $chocoPath = Get-Command choco -ErrorAction SilentlyContinue
        if ($chocoPath) {
            Write-ColorOutput "✅ Chocolatey instalado com sucesso!" "Success"
            return $true
        } else {
            Write-ColorOutput "❌ Falha na instalação do Chocolatey" "Error"
            return $false
        }
    }
    catch {
        Write-ColorOutput "❌ Erro ao instalar Chocolatey: $($_.Exception.Message)" "Error"
        return $false
    }
}

function Install-OpenSSLChocolatey {
    Write-ColorOutput "🔐 Instalando OpenSSL via Chocolatey..." "Info"
    
    try {
        # Atualizar Chocolatey
        Write-ColorOutput "🔄 Atualizando Chocolatey..." "Info"
        & choco upgrade chocolatey -y | Out-Null
        
        # Instalar OpenSSL
        Write-ColorOutput "📦 Instalando OpenSSL..." "Info"
        & choco install openssl -y | Out-Null
        
        # Verificar instalação
        if (Test-OpenSSL) {
            Write-ColorOutput "✅ OpenSSL instalado com sucesso via Chocolatey!" "Success"
            return $true
        } else {
            Write-ColorOutput "❌ Falha na instalação via Chocolatey" "Error"
            return $false
        }
    }
    catch {
        Write-ColorOutput "❌ Erro ao instalar OpenSSL via Chocolatey: $($_.Exception.Message)" "Error"
        return $false
    }
}

function Install-OpenSSLDirect {
    Write-ColorOutput "🔐 Instalando OpenSSL via download direto..." "Info"
    
    try {
        # URL do OpenSSL (versão mais recente)
        $opensslUrl = "https://slproweb.com/download/Win64OpenSSL-3_1_4.exe"
        $installerPath = "$env:TEMP\Win64OpenSSL-3_1_4.exe"
        
        Write-ColorOutput "📥 Baixando instalador OpenSSL..." "Info"
        
        # Baixar instalador
        $webClient = New-Object System.Net.WebClient
        $webClient.Headers.Add("User-Agent", "PowerShell")
        $webClient.DownloadFile($opensslUrl, $installerPath)
        
        if (Test-Path $installerPath) {
            Write-ColorOutput "✅ Download concluído: $installerPath" "Success"
            
            # Executar instalador silenciosamente
            Write-ColorOutput "🔧 Executando instalador..." "Info"
            $process = Start-Process -FilePath $installerPath -ArgumentList "/S" -Wait -PassThru
            
            if ($process.ExitCode -eq 0) {
                Write-ColorOutput "✅ Instalador executado com sucesso!" "Success"
                
                # Adicionar ao PATH
                $opensslPath = "C:\Program Files\OpenSSL-Win64\bin"
                if (Test-Path $opensslPath) {
                    $currentPath = [Environment]::GetEnvironmentVariable("PATH", "Machine")
                    if ($currentPath -notlike "*$opensslPath*") {
                        $newPath = "$currentPath;$opensslPath"
                        [Environment]::SetEnvironmentVariable("PATH", $newPath, "Machine")
                        Write-ColorOutput "✅ OpenSSL adicionado ao PATH do sistema" "Success"
                    }
                    
                    # Atualizar PATH da sessão atual
                    $env:PATH = "$env:PATH;$opensslPath"
                    
                    # Verificar instalação
                    if (Test-OpenSSL) {
                        Write-ColorOutput "✅ OpenSSL instalado com sucesso!" "Success"
                        return $true
                    }
                }
            } else {
                Write-ColorOutput "❌ Instalador falhou com código: $($process.ExitCode)" "Error"
            }
        } else {
            Write-ColorOutput "❌ Falha no download do instalador" "Error"
        }
        
        # Limpar arquivo temporário
        if (Test-Path $installerPath) {
            Remove-Item $installerPath -Force
        }
        
        return $false
    }
    catch {
        Write-ColorOutput "❌ Erro ao instalar OpenSSL diretamente: $($_.Exception.Message)" "Error"
        return $false
    }
}

function Test-OpenSSLInstallation {
    Write-ColorOutput "🔍 Testando instalação do OpenSSL..." "Info"
    
    try {
        # Testar comando básico
        $testKey = "$env:TEMP\test_key.pem"
        & openssl genrsa -out $testKey 2048 2>$null
        
        if (Test-Path $testKey) {
            Write-ColorOutput "✅ Teste de geração de chave RSA bem-sucedido!" "Success"
            Remove-Item $testKey -Force
            return $true
        } else {
            Write-ColorOutput "❌ Falha no teste de geração de chave" "Error"
            return $false
        }
    }
    catch {
        Write-ColorOutput "❌ Erro no teste: $($_.Exception.Message)" "Error"
        return $false
    }
}

# Função principal
function Main {
    Write-ColorOutput "🚀 INSTALADOR AUTOMÁTICO OPENSSL" "Info"
    Write-ColorOutput "=================================" "Info"
    Write-ColorOutput ""
    
    # Verificar se já está instalado
    if (Test-OpenSSL) {
        Write-ColorOutput "🎯 OpenSSL já está funcionando! Nenhuma instalação necessária." "Success"
        return
    }
    
    Write-ColorOutput "📋 OpenSSL não encontrado. Iniciando instalação..." "Info"
    Write-ColorOutput ""
    
    # Tentar instalar via Chocolatey primeiro
    Write-ColorOutput "🔄 MÉTODO 1: Tentando instalação via Chocolatey..." "Info"
    
    # Verificar se Chocolatey está instalado
    $chocoInstalled = Get-Command choco -ErrorAction SilentlyContinue
    if (-not $chocoInstalled) {
        Write-ColorOutput "🍫 Chocolatey não encontrado. Instalando..." "Info"
        if (-not (Install-Chocolatey)) {
            Write-ColorOutput "❌ Falha na instalação do Chocolatey. Tentando método alternativo..." "Warning"
        }
    }
    
    # Tentar instalar OpenSSL via Chocolatey
    if (Get-Command choco -ErrorAction SilentlyContinue) {
        if (Install-OpenSSLChocolatey) {
            Write-ColorOutput "🎉 Instalação via Chocolatey bem-sucedida!" "Success"
            goto TestInstallation
        }
    }
    
    # Se Chocolatey falhou, tentar instalação direta
    Write-ColorOutput "🔄 MÉTODO 2: Tentando instalação direta..." "Info"
    if (Install-OpenSSLDirect) {
        Write-ColorOutput "🎉 Instalação direta bem-sucedida!" "Success"
        goto TestInstallation
    }
    
    # Se ambos falharam
    Write-ColorOutput "❌ Todos os métodos de instalação falharam!" "Error"
    Write-ColorOutput "💡 Tente instalar manualmente:" "Info"
    Write-ColorOutput "   1. Baixe de: https://slproweb.com/products/Win32OpenSSL.html" "Info"
    Write-ColorOutput "   2. Execute como administrador" "Info"
    Write-ColorOutput "   3. Adicione ao PATH: C:\Program Files\OpenSSL-Win64\bin" "Info"
    return
    
    :TestInstallation
    Write-ColorOutput ""
    Write-ColorOutput "🧪 Testando instalação..." "Info"
    if (Test-OpenSSLInstallation) {
        Write-ColorOutput ""
        Write-ColorOutput "🎉 INSTALAÇÃO CONCLUÍDA COM SUCESSO!" "Success"
        Write-ColorOutput "=====================================" "Success"
        Write-ColorOutput ""
        Write-ColorOutput "✅ OpenSSL está funcionando corretamente" "Success"
        Write-ColorOutput "🎯 Agora você pode executar o gerador de certificados" "Success"
        Write-ColorOutput "🔗 Acesse: fiscal/criar_certificado_shell.php" "Info"
    } else {
        Write-ColorOutput ""
        Write-ColorOutput "❌ PROBLEMA NA INSTALAÇÃO!" "Error"
        Write-ColorOutput "=========================" "Error"
        Write-ColorOutput "💡 Tente reiniciar o PowerShell e executar novamente" "Warning"
    }
}

# Executar script
try {
    Main
}
catch {
    Write-ColorOutput "❌ ERRO CRÍTICO: $($_.Exception.Message)" "Error"
    Write-ColorOutput "📋 Stack Trace:" "Error"
    Write-ColorOutput $_.ScriptStackTrace "Error"
}
finally {
    Write-ColorOutput ""
    Write-ColorOutput "⏸️ Pressione qualquer tecla para sair..." "Info"
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
}
