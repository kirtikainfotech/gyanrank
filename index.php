<?php
$reactApp = __DIR__ . '/react-app/index.html';

if (!is_file($reactApp)) {
    http_response_code(500);
    echo 'React website not found.';
    exit;
}

$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$baseHref = ($basePath === '' || $basePath === '.') ? '/' : $basePath . '/';
$html = file_get_contents($reactApp);
if ($html === false) {
    http_response_code(500);
    echo 'React website could not be loaded.';
    exit;
}
$baseTag = '<base href="' . htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8') . '">';
if (stripos($html, '<base ') === false) {
    $html = preg_replace('/<head(\s*[^>]*)>/i', '<head$1>' . "\n    " . $baseTag, $html, 1) ?? $html;
}

$forgotUrl = $baseHref . 'forgot-password';
$whatsappNumber = '918299442665';
$whatsappText = rawurlencode('Hello Gyan Rank support, I need help with my account.');
$supportMarkup = <<<HTML

<style>
.gyanrank-whatsapp-support{position:fixed;right:22px;bottom:22px;z-index:9999;width:54px;height:54px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#25d366;color:#fff;box-shadow:0 14px 34px rgba(7,94,84,.24);border:3px solid #fff;transition:transform .18s ease,box-shadow .18s ease}
.gyanrank-whatsapp-support:hover{transform:translateY(-2px);box-shadow:0 18px 42px rgba(7,94,84,.32)}
.gyanrank-whatsapp-support svg{width:29px;height:29px;fill:currentColor}
.gyanrank-login-helper{display:flex;justify-content:flex-end;margin:-6px 0 14px;font:600 14px Arial,sans-serif}
.gyanrank-login-helper a{color:#004074;text-decoration:none}
.gyanrank-login-helper a:hover{color:#f68a00}
@media(max-width:640px){.gyanrank-whatsapp-support{right:16px;bottom:78px;width:50px;height:50px}}
</style>
<a class="gyanrank-whatsapp-support" href="https://wa.me/{$whatsappNumber}?text={$whatsappText}" target="_blank" rel="noopener" aria-label="Chat with Gyan Rank support on WhatsApp">
    <svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16.01 3.2A12.73 12.73 0 0 0 5.09 22.5L3.4 28.8l6.46-1.69A12.75 12.75 0 1 0 16.01 3.2Zm0 2.28a10.47 10.47 0 0 1 8.9 15.98l-.27.43.96 3.55-3.65-.95-.41.24A10.48 10.48 0 0 1 7.2 10.4a10.41 10.41 0 0 1 8.81-4.92Zm-4.15 5.63c-.2 0-.52.08-.79.37-.27.3-1.04 1.02-1.04 2.47s1.06 2.86 1.2 3.05c.15.2 2.05 3.28 5.06 4.46 2.5.98 3.02.79 3.56.74.55-.05 1.78-.73 2.03-1.43.25-.7.25-1.3.18-1.43-.08-.13-.28-.2-.58-.35-.3-.15-1.77-.87-2.04-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.27-.47-2.42-1.49-.9-.8-1.5-1.79-1.67-2.09-.18-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52-.07-.15-.66-1.6-.91-2.18-.24-.58-.49-.5-.67-.51h-.56Z"/></svg>
</a>
<script>
(function(){
    var forgotUrl = "{$forgotUrl}";
    function attachForgotLink(){
        if (String(window.location.hash || '').indexOf('/login') === -1) return;
        if (document.querySelector('.auth-forgot a[href*="forgot-password"]')) return;
        if (document.querySelector('.gyanrank-login-helper')) return;
        var password = Array.prototype.slice.call(document.querySelectorAll('input[type="password"]')).find(function(input){
            return input.offsetParent !== null;
        });
        if (!password) return;
        var row = document.createElement('div');
        row.className = 'gyanrank-login-helper';
        row.innerHTML = '<a href="' + forgotUrl + '">Forgot password?</a>';
        var parent = password.closest('label') || password.parentElement;
        if (parent) parent.insertAdjacentElement('afterend', row);
    }
    window.addEventListener('hashchange', attachForgotLink);
    new MutationObserver(attachForgotLink).observe(document.documentElement, {childList:true, subtree:true});
    setTimeout(attachForgotLink, 250);
    setTimeout(attachForgotLink, 900);
})();
</script>
HTML;

if (stripos($html, '</body>') !== false) {
    $html = str_ireplace('</body>', $supportMarkup . "\n</body>", $html);
} else {
    $html .= $supportMarkup;
}

header('Content-Type: text/html; charset=UTF-8');
$lastModified = filemtime($reactApp) ?: time();
$etag = '"' . md5($html) . '"';
header('Cache-Control: public, max-age=60');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
header('ETag: ' . $etag);

$ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
$ifModifiedSince = strtotime((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '')) ?: 0;
if ($ifNoneMatch === $etag || $ifModifiedSince >= $lastModified) {
    http_response_code(304);
    exit;
}

echo $html;
