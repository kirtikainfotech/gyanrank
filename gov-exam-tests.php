<?php
require_once __DIR__ . '/config.php';

function rows_safe(string $sql): array { try { return db()->query($sql)->fetch_all(MYSQLI_ASSOC); } catch (Throwable $e) { return []; } }
function exam_logo_page(?string $slug, string $name = ''): string {
    $slug = trim((string) $slug);
    if ($slug === '') {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
    }
    foreach (['svg','png','jpg','jpeg','webp'] as $ext) {
        $rel = 'uploads/exam-logos/' . $slug . '.' . $ext;
        if (is_file(__DIR__ . '/' . $rel)) {
            return app_url($rel);
        }
    }
    return '';
}
function abbr_page(string $s): string {
    $parts = preg_split('/\s+/', trim($s)) ?: [];
    $out = '';
    foreach ($parts as $part) {
        if ($part !== '') $out .= strtoupper(substr($part, 0, 1));
        if (strlen($out) >= 3) break;
    }
    return $out ?: 'EX';
}

$categories = rows_safe("SELECT c.id,c.name,c.slug,COUNT(DISTINCT m.id) mocks FROM gov_exam_categories c LEFT JOIN gov_exam_categories ch ON ch.parent_id=c.id LEFT JOIN gov_exam_mock_tests m ON m.status='published' AND (m.category_id=c.id OR m.subcategory_id=c.id OR m.category_id=ch.id OR m.subcategory_id=ch.id) WHERE c.status='active' GROUP BY c.id,c.name,c.slug HAVING mocks>0 ORDER BY mocks DESC,c.name LIMIT 80");
$mocks = rows_safe("SELECT m.id,m.title,m.duration_minutes,c.name category_name,c.slug category_slug,s.name subcategory_name,s.slug subcategory_slug,(SELECT COUNT(*) FROM gov_exam_mock_questions q WHERE q.mock_test_id=m.id AND q.status='active') question_count FROM gov_exam_mock_tests m LEFT JOIN gov_exam_categories c ON c.id=m.category_id LEFT JOIN gov_exam_categories s ON s.id=m.subcategory_id WHERE m.status='published' ORDER BY m.id DESC LIMIT 120");
$pageTitle = 'Government Exam Mock Tests - ' . app_name();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--nav:#07111f;--ink:#07111f;--muted:#66758a;--line:#d9e4ef;--bg:#f3f8fd;--blue:#155dfc;--yellow:#ffdf3b}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Poppins,Arial,sans-serif}a{text-decoration:none;color:inherit}.wrap{width:min(1240px,94%);margin:auto}.top{background:var(--nav);position:sticky;top:0;z-index:40}.topin{height:62px;display:flex;align-items:center;justify-content:space-between;gap:18px}.logo img{height:52px}.nav{display:flex;gap:22px;color:#eaf2ff;font-size:13px;font-weight:600}.btn{display:inline-flex;align-items:center;justify-content:center;height:38px;padding:0 14px;border-radius:10px;border:1px solid #d4dfeb;background:#fff;font-size:12px;font-weight:700}.btn.primary{background:#ff8500;border-color:#ff8500;color:#fff}.pageHead{padding:28px 0 18px}.pageHead h1{margin:0;font-size:30px;line-height:1.15}.pageHead p{margin:7px 0 0;color:var(--muted);font-size:13px}.layout{display:grid;grid-template-columns:300px 1fr;gap:20px;align-items:start;padding-bottom:38px}.filters{position:sticky;top:82px;background:#fff;border-right:1px solid var(--line);padding:10px 18px 18px 0;min-height:calc(100vh - 154px);max-height:calc(100vh - 84px);overflow:auto}.filterBlock{border-bottom:1px solid #e6edf5;padding:0 0 16px;margin-bottom:18px}.filterBlock:last-child{border-bottom:0;margin-bottom:0}.filters h2{font-size:17px;margin:0 0 12px}.search{height:44px;border:1px solid #d7e2ee;border-radius:10px;background:#fff;display:flex;align-items:center;gap:9px;padding:0 12px;margin-bottom:12px;color:#94a3b8}.search input{border:0;outline:0;width:100%;font:inherit;font-size:12px}.checks{display:grid;gap:10px;max-height:430px;overflow:auto;padding-right:6px}.check{display:flex;align-items:center;gap:11px;color:#0f172a;font-size:14px}.check input{appearance:none;width:18px;height:18px;border:1px solid #94a3b8;border-radius:3px;background:#fff}.check input:checked{background:var(--blue);border-color:var(--blue);box-shadow:inset 0 0 0 4px #fff}.mobileFilter{display:none}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.testCard{border:1px solid #d4dce6;border-radius:10px;background:#fff;overflow:hidden;box-shadow:0 10px 24px rgba(15,23,42,.055);display:flex;flex-direction:column;min-height:292px}.testTop{position:relative;padding:16px}.testLogo{width:34px;height:34px;border-radius:10px;border:1px solid #d7e5f4;background:#fff;display:grid;place-items:center;color:#155dfc;font-size:10px;font-weight:800;overflow:hidden}.testLogo img{width:100%;height:100%;object-fit:contain;padding:3px;background:#fff}.pill{position:absolute;right:16px;top:18px;max-width:104px;padding:6px 8px;border-radius:7px;background:#e5e7eb;color:#4b5563;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.testCard h3{margin:14px 0 10px;font-size:16px;line-height:1.28;font-weight:700;min-height:62px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.meta{display:grid;grid-template-columns:1fr 1fr;gap:9px;color:#475569;font-size:12px}.meta span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.free{display:inline-block;margin-left:3px;padding:2px 5px;border-radius:5px;background:#00a63e;color:#fff;font-size:10px;font-weight:700}.star{color:#f6b700}.testFoot{margin-top:auto;background:#eef6ff;padding:14px 16px}.testBtn{height:44px;border-radius:8px;background:var(--yellow);display:grid;place-items:center;font-size:15px;font-weight:700}.empty{grid-column:1/-1;background:#fff;border:1px solid var(--line);border-radius:12px;padding:28px;text-align:center;color:var(--muted)}
.testCard{position:relative;min-height:252px;border-color:#d7e4f2;border-radius:12px;box-shadow:0 12px 28px rgba(15,23,42,.06);transition:.18s ease}.testCard:hover{transform:translateY(-3px);box-shadow:0 18px 40px rgba(15,23,42,.1);border-color:#bdd5ee}.testCard:before{content:"";position:absolute;left:0;right:0;top:0;height:3px;background:linear-gradient(90deg,#155dfc,#ff8500);opacity:.85}.testTop{padding:18px 20px 14px}.testLogo{width:42px;height:42px;border-radius:12px;padding:4px;box-shadow:0 8px 18px rgba(15,23,42,.08)}.pill{right:20px;top:22px;border-radius:8px;background:#edf1f5;color:#526071;font-size:11px;font-weight:600}.testCard h3{margin:16px 0 18px;font-size:16.5px;line-height:1.32;min-height:44px;-webkit-line-clamp:2;color:#07111f}.meta{grid-template-columns:1fr 1fr;gap:10px 14px;font-size:12px;color:#52637a}.meta span{display:flex;align-items:center;gap:4px}.free{font-size:10px;border-radius:999px;padding:2px 6px}.testFoot{padding:14px 20px 16px;background:linear-gradient(180deg,#eef6ff,#eaf4ff)}.testBtn{height:42px;border-radius:9px;background:linear-gradient(180deg,#ffe86a,#ffd92f);box-shadow:inset 0 -1px 0 rgba(0,0,0,.12);transition:.15s ease}.testBtn:hover{transform:translateY(-1px);background:linear-gradient(180deg,#ffe45a,#ffd31d)}.testCard{min-height:224px}.testTop{padding:14px 16px 11px}.testLogo{width:36px;height:36px;border-radius:10px}.pill{right:16px;top:17px;padding:5px 8px;font-size:10.5px}.testCard h3{margin:13px 0 13px;font-size:15px;line-height:1.28;min-height:38px}.meta{gap:7px 12px;font-size:11px}.free{font-size:9px;padding:2px 5px}.testFoot{padding:11px 16px 13px}.testBtn{height:38px;font-size:13.5px;border-radius:8px}.footer{background:#07111f;color:#cbd5e1;margin-top:10px}.foot{display:grid;grid-template-columns:1.35fr repeat(4,1fr);gap:34px;padding:30px 0 22px;align-items:start}.footBrand img{height:48px;width:auto;margin-bottom:12px}.foot h4{margin:0 0 12px;color:#fff;font-size:13px;font-weight:700}.foot a,.foot p{display:block;color:#b8c7d6;font-size:12px;line-height:1.68;margin:0}.foot a{margin-bottom:7px}.foot a:hover{color:#fff}.footText{max-width:340px}.footSmall{margin-top:8px;color:#8fa2b8!important;font-size:11.5px!important;line-height:1.55!important}.footLine{width:34px;height:2px;background:#2563eb;margin-top:15px}.supportBox{padding:0}.contactLine{display:block;margin-bottom:7px;color:#b8c7d6;font-size:12px;line-height:1.6}.contactLine b{display:block;color:#93c5fd;font-size:10.5px;letter-spacing:.04em;text-transform:uppercase}.footBottom{border-top:1px solid rgba(255,255,255,.1);padding:14px 0 16px;display:flex;align-items:center;justify-content:space-between;gap:14px;color:#8fa2b8;font-size:11.5px}.footBottom a{color:#cbd5e1;margin-left:16px}.footBottom strong{color:#e2e8f0;font-weight:600}@media(max-width:900px){.nav{display:none}.topin{height:58px;justify-content:center}.logo img{height:44px}.pageHead{padding:18px 0 12px}.pageHead h1{font-size:23px}.layout{display:block}.filters{display:none}.mobileFilter{display:flex;gap:8px;overflow:auto;margin:0 -14px 12px;padding:2px 14px 10px;scrollbar-width:none}.mobileFilter::-webkit-scrollbar{display:none}.mobileFilter button{flex:0 0 auto;border:1px solid #d7e4f2;background:#fff;border-radius:999px;min-height:34px;padding:0 12px;font-size:11px;font-weight:700;color:#334155}.mobileFilter button.active{background:#07111f;color:#fff;border-color:#07111f}.wrap{width:100%;padding:0 14px}.grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.testCard{min-height:190px;border-radius:14px}.testTop{padding:10px}.testLogo{width:40px;height:40px}.pill{position:static;display:inline-flex;margin-top:8px;max-width:100%;padding:3px 7px;border-radius:999px;background:#eef6ff;color:#155dfc;font-size:9px;font-weight:600}.testCard h3{font-size:12.2px;line-height:1.28;min-height:31px;margin:8px 0;-webkit-line-clamp:2}.meta{grid-template-columns:1fr;gap:3px;font-size:9px}.meta span:nth-child(n+3){display:none}.free{font-size:8px;padding:1px 4px}.testFoot{padding:8px 10px 10px}.testBtn{height:31px;border-radius:9px;font-size:11px}.btn{display:none}.foot{grid-template-columns:1fr;gap:18px;padding:24px 14px 18px}.footBottom{display:grid;padding-left:14px;padding-right:14px}.footBottom a{margin-left:0;margin-right:14px}}
</style>
</head>
<body>
<header class="top"><div class="wrap topin"><a class="logo" href="<?= h(app_url('index')); ?>"><img src="<?= h(app_url('assets/grlogo.png')); ?>" alt="GYAN NEXA"></a><nav class="nav"><a href="<?= h(app_url('index')); ?>">Home</a><a href="<?= h(app_url('gov-exam-tests')); ?>">Govt Exam Prep</a><a href="<?= h(app_url('gov-exam-tests')); ?>">Mock Tests</a><a href="<?= h(app_url('login')); ?>">Login</a></nav><a class="btn primary" href="<?= h(app_url('login')); ?>">Create Account</a></div></header>
<main class="wrap"><section class="pageHead"><h1>Government Exam Mock Tests</h1><p>Exam category aur test series select karke preparation start karein.</p></section><div class="mobileFilter"><button class="active" data-mobile-filter="all">All Exams</button><?php foreach(array_slice($categories,0,10) as $cat): ?><button data-mobile-filter="<?= h($cat['slug']); ?>"><?= h($cat['name']); ?></button><?php endforeach; ?></div><section class="layout"><aside class="filters"><div class="filterBlock"><h2>Exam Category</h2><label class="search"><span>⌕</span><input type="search" data-filter-search="cat" placeholder="Search Exam Category"></label><div class="checks" data-filter-list="cat"><?php foreach($categories as $cat): ?><label class="check"><input type="checkbox" class="filterCat" value="<?= h($cat['slug']); ?>"><span><?= h($cat['name']); ?></span></label><?php endforeach; ?></div></div></aside><div><div class="grid" id="testGrid"><?php foreach($mocks as $m): $q=(int)($m['question_count'] ?? 0); $tests=max(1,(int)ceil(max(1,$q)/50)); $free=max(1,min($tests,(int)ceil($tests*.18))); $rating=number_format(4.1+(((int)$m['id']%7)/10),1); $cat=$m['category_name'] ?: 'Exam'; $exam=$m['subcategory_name'] ?: $cat; $catSlug=$m['category_slug'] ?: ''; $examSlug=$m['subcategory_slug'] ?: $catSlug; $logo=exam_logo_page($examSlug,$exam) ?: exam_logo_page($catSlug,$cat); ?><article class="testCard" data-cat="<?= h(trim($catSlug . ' ' . $examSlug)); ?>" data-name="<?= h(strtolower($m['title'].' '.$cat.' '.$exam)); ?>"><div class="testTop"><span class="testLogo"><?php if($logo): ?><img src="<?= h($logo); ?>" alt="<?= h($exam); ?>"><?php else: ?><?= h(abbr_page($exam)); ?><?php endif; ?></span><span class="pill"><?= h($cat); ?></span><h3><?= h($m['title']); ?></h3><div class="meta"><span><?= h((string)$tests); ?> Tests <b class="free"><?= h((string)$free); ?> Tests Free</b></span><span><?= h($rating); ?> <b class="star">★</b> (<?= h(number_format(max(1,(int)round($q/3)))); ?>)</span><span>English, Hindi</span><span><?= h((string)max(1,$q)); ?> Questions</span></div></div><div class="testFoot"><a class="testBtn" href="<?= h(app_url('mock-test-detail?id='.(int)$m['id'])); ?>">View Test Series</a></div></article><?php endforeach; ?><div class="empty" id="emptyState" hidden>No matching test series found.</div></div></div></section></main>
<footer class="footer"><div class="wrap foot"><div class="footBrand"><img src="<?= h(app_url('assets/grlogo.png')); ?>" alt="GYAN NEXA"><p class="footText">GYAN NEXA par students government exam mock tests, courses aur focused practice content ek organized platform se access kar sakte hain.</p><p class="footSmall">Simple navigation, clean test series listing and learner-friendly exam preparation.</p><div class="footLine"></div></div><div><h4>Company</h4><a href="<?= h(app_url('about')); ?>">About Us</a><a href="<?= h(app_url('contact')); ?>">Contact</a><a href="<?= h(app_url('ranking')); ?>">Institute Ranking</a><a href="<?= h(app_url('register-institution')); ?>">Institute Manage</a></div><div><h4>Learning</h4><a href="<?= h(app_url('index')); ?>#courses">Popular Courses</a><a href="<?= h(app_url('gov-exam-tests')); ?>">Govt Exam Prep</a><a href="<?= h(app_url('gov-exam-tests')); ?>">Mock Tests</a><a href="<?= h(app_url('login')); ?>">Student Login</a></div><div><h4>Exam Focus</h4><a href="<?= h(app_url('gov-exam-tests')); ?>">SSC Exams</a><a href="<?= h(app_url('gov-exam-tests')); ?>">Teaching Exams</a><a href="<?= h(app_url('gov-exam-tests')); ?>">Railways Exams</a><a href="<?= h(app_url('gov-exam-tests')); ?>">Civil Services</a></div><div><h4>Support</h4><div class="supportBox"><div class="contactLine"><b>Phone</b><span>+91 8299442665</span></div><div class="contactLine"><b>Hours</b><span>10 AM to 6 PM</span></div><div class="contactLine"><b>Help</b><span>Student account, mock tests and institute support</span></div></div></div></div><div class="wrap footBottom"><span><strong>GYAN NEXA</strong> Copyright <?= h(date('Y')); ?>. All rights reserved.</span><span><a href="<?= h(app_url('terms-and-conditions')); ?>">Terms</a><a href="<?= h(app_url('privacy')); ?>">Privacy</a><a href="<?= h(app_url('contact')); ?>">Support</a></span></div></footer><script>
(function(){
  var cards = [].slice.call(document.querySelectorAll('.testCard'));
  var empty = document.getElementById('emptyState');
  function selectedCats(){
    return [].slice.call(document.querySelectorAll('.filterCat:checked')).map(function(x){ return x.value; });
  }
  function cardHasCat(card, cat){
    return (' ' + (card.dataset.cat || '') + ' ').indexOf(' ' + cat + ' ') > -1;
  }
  function apply(extraCat){
    var cats = selectedCats();
    if (extraCat && extraCat !== 'all') { cats = [extraCat]; }
    var shown = 0;
    cards.forEach(function(card){
      var ok = !cats.length || cats.some(function(cat){ return cardHasCat(card, cat); });
      card.style.display = ok ? 'flex' : 'none';
      if (ok) { shown++; }
    });
    if (empty) { empty.hidden = shown !== 0; }
  }
  document.querySelectorAll('.filterCat').forEach(function(x){
    x.addEventListener('change', function(){ apply(); });
  });
  document.querySelectorAll('[data-filter-search="cat"]').forEach(function(input){
    input.addEventListener('input', function(){
      var list = document.querySelector('[data-filter-list="cat"]');
      var q = input.value.toLowerCase();
      if (!list) { return; }
      list.querySelectorAll('.check').forEach(function(row){
        row.style.display = row.textContent.toLowerCase().indexOf(q) > -1 ? 'flex' : 'none';
      });
    });
  });
  document.querySelectorAll('[data-mobile-filter]').forEach(function(btn){
    btn.addEventListener('click', function(){
      document.querySelectorAll('[data-mobile-filter]').forEach(function(b){ b.classList.remove('active'); });
      btn.classList.add('active');
      document.querySelectorAll('.filterCat').forEach(function(x){ x.checked = false; });
      apply(btn.dataset.mobileFilter);
    });
  });
})();
</script>
</body>
</html>