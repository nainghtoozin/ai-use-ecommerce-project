# Get the XSRF-TOKEN from the cookie and use it as X-XSRF-TOKEN header
$session = $null

# First GET to get session cookies
$loginPageResponse = Invoke-WebRequest -Uri "http://localhost:8000/store/default/login" -Method GET -SessionVariable session -UseBasicParsing
Write-Host "GET /store/default/login status: $($loginPageResponse.StatusCode)"

# Get XSRF-TOKEN cookie value
$xsrfCookie = $session.Cookies.GetCookies("http://localhost:8000") | Where-Object { $_.Name -eq "XSRF-TOKEN" } | Select-Object -First 1
Write-Host "XSRF-TOKEN from cookie: $($xsrfCookie.Value)"
Write-Host ""

# Now POST the login with the session cookies and the XSRF token as header
$headers = @{
    "X-XSRF-TOKEN" = $xsrfCookie.Value
    "Content-Type" = "application/x-www-form-urlencoded"
    "X-Requested-With" = "XMLHttpRequest"
    "Accept" = "application/json"
}

$body = @{
    email = "myat@gmail.com"
    password = "password"
    remember = $false
}

Write-Host "=== POSTING LOGIN ==="
Write-Host "Body: email=myat@gmail.com password=password"
$loginResponse = Invoke-WebRequest -Uri "http://localhost:8000/store/default/login" -Method POST -Headers $headers -Body $body -WebSession $session -UseBasicParsing -SkipCertificateCheck
Write-Host "POST status: $($loginResponse.StatusCode)"
Write-Host "Content: $($loginResponse.Content)"
