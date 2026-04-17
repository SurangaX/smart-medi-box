# create_and_upload: decode base64 from test_payload_article.json to tmp_upload.jpg and POST as multipart
$base = "c:\Users\Suran\Documents\smart-medi-box"
$jsonFile = Join-Path $base 'test_payload_article.json'
$tmpFile = Join-Path $base 'tmp_upload.jpg'
$respFile = Join-Path $base 'create_multipart_response.json'

$s = Get-Content -Raw $jsonFile
$j = $s | ConvertFrom-Json
$d = $j.cover_image_data_url
if (-not $d) { Write-Error "No data URL found"; exit 1 }
$b64 = ($d -split ',')[1] -replace '%0A','' -replace '\s+',''
[System.IO.File]::WriteAllBytes($tmpFile, [Convert]::FromBase64String($b64))
Write-Host "WROTE $tmpFile ($(Get-Item $tmpFile).Length bytes)"

$form = @{
    user_id = 16
    title = 'multipart high quality test'
    content = 'multipart upload test'
    cover_file = Get-Item $tmpFile
}

$uri = 'https://smart-medi-box.onrender.com/index.php/api/articles/create'
try {
    $r = Invoke-WebRequest -Uri $uri -Method Post -Form $form -UseBasicParsing -OutFile $respFile -ErrorAction Stop
    Write-Host "Upload response saved to $respFile"
    Get-Content $respFile -Raw | Write-Host
} catch {
    Write-Error "Upload failed: $_"
    exit 1
}
