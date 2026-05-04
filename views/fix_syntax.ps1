$path = 'c:\xampp\htdocs\ersan_elk\views\home.php'
$content = [System.IO.File]::ReadAllText($path)
$badBlock = "`r`n                    }`r`n                        requestIdleCallback(() => revealPhase2Widgets(), { timeout: 500 });`r`n                    } else {`r`n                        setTimeout(revealPhase2Widgets, 350);`r`n                    }"
$content = $content.Replace($badBlock, "`r`n                    }")
[System.IO.File]::WriteAllText($path, $content, [System.Text.Encoding]::UTF8)
Write-Host "Syntax fixed."
