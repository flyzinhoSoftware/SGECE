$ErrorActionPreference = 'Stop'

$phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
if ($phpCommand) {
    $php = $phpCommand.Source
} elseif (Test-Path 'C:\xampp\php\php.exe') {
    $php = 'C:\xampp\php\php.exe'
} else {
    throw 'PHP não encontrado. Adicione php.exe ao PATH ou instale o XAMPP em C:\xampp.'
}

$name = Read-Host 'Nome do administrador'
$email = Read-Host 'E-mail do administrador'
$securePassword = Read-Host 'Palavra-passe (mínimo 12 caracteres)' -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
$exitCode = 1

try {
    $env:SGECE_ADMIN_NAME = $name
    $env:SGECE_ADMIN_EMAIL = $email
    $env:SGECE_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)

    & $php (Join-Path $PSScriptRoot 'create-admin.php')
    $exitCode = $LASTEXITCODE
} finally {
    Remove-Item Env:SGECE_ADMIN_NAME -ErrorAction SilentlyContinue
    Remove-Item Env:SGECE_ADMIN_EMAIL -ErrorAction SilentlyContinue
    Remove-Item Env:SGECE_ADMIN_PASSWORD -ErrorAction SilentlyContinue
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
}

exit $exitCode
