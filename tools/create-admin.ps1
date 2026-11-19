param(
    [Parameter(Mandatory=$true)][string]$Name,
    [Parameter(Mandatory=$true)][string]$Email,
    [string]$Role = 'super-admin',
    [string]$PhpPath = 'C:\wamp64\bin\php\php8.2.29\php.exe'
)
$securePassword = Read-Host 'Administrator password (12+ characters)' -AsSecureString
$passwordPointer = [IntPtr]::Zero
$plainPassword = $null
try {
    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
    $plainPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    $env:MULTIFOREX_ADMIN_PASSWORD = $plainPassword
    & $PhpPath (Join-Path $PSScriptRoot 'create-admin.php') "--name=$Name" "--email=$Email" "--role=$Role"
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
} finally {
    Remove-Item Env:MULTIFOREX_ADMIN_PASSWORD -ErrorAction SilentlyContinue
    if ($passwordPointer -ne [IntPtr]::Zero) { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer) }
    $plainPassword = $null
    $securePassword.Dispose()
}
