$tools = @{
    pdftotext = if ($env:PDFTOTEXT_BINARY) { $env:PDFTOTEXT_BINARY } else { 'pdftotext' }
    pdftoppm = if ($env:PDFTOPPM_BINARY) { $env:PDFTOPPM_BINARY } else { 'pdftoppm' }
    pdfinfo = if ($env:PDFINFO_BINARY) { $env:PDFINFO_BINARY } else { 'pdfinfo' }
    tesseract = if ($env:TESSERACT_BINARY) { $env:TESSERACT_BINARY } else { 'tesseract' }
    qpdf = if ($env:QPDF_BINARY) { $env:QPDF_BINARY } else { 'qpdf' }
}

foreach ($name in $tools.Keys) {
    $command = Get-Command $tools[$name] -ErrorAction SilentlyContinue
    if (-not $command) {
        $candidate = switch ($name) {
            'qpdf' { Get-ChildItem -LiteralPath 'C:\Program Files' -Filter 'qpdf.exe' -File -Recurse -ErrorAction SilentlyContinue }
            'tesseract' { Get-Item -LiteralPath 'C:\Program Files\Tesseract-OCR\tesseract.exe' -ErrorAction SilentlyContinue }
            default { Get-ChildItem -LiteralPath "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Filter "$name.exe" -File -Recurse -ErrorAction SilentlyContinue }
        }
        $command = @($candidate)[-1]
    }
    if (-not $command) {
        "$name`tNOT FOUND`t$($tools[$name])"
        continue
    }
    $source = if ($command.Source) { $command.Source } else { $command.FullName }
    $versionArg = if ($name -in @('pdftotext', 'pdftoppm', 'pdfinfo')) { '-v' } else { '--version' }
    $version = & $source $versionArg 2>&1 | Out-String
    "$name`tFOUND`t$source`t$($version.Trim())"
}
