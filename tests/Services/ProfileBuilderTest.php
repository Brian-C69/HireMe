<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Services\Resume\Builder\HtmlProfileBuilder;
use App\Services\Resume\Builder\JsonProfileBuilder;
use App\Services\Resume\Builder\ProfileDirector;

$director = new ProfileDirector();
$htmlBuilder = new HtmlProfileBuilder();

$htmlData = [
    'name' => 'Ada Lovelace',
    'headline' => 'Mathematician & Writer',
    'email' => 'ada@example.com',
    'phone' => '+1 555-1234',
    'location' => 'London',
    'summary' => "First programmer & visionary.\nWorking on \"Analytical\" Engine.",
    'experience' => [
        [
            'role' => 'Lead Analyst',
            'company' => 'Babbage Engines',
            'period' => '1833-1842',
            'description' => "Developed algorithms\nDocumented notes",
        ],
    ],
    'skills' => ['Mathematics', 'Programming'],
];

$html = $director->buildFullProfile($htmlBuilder, $htmlData);

$expectedHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ada Lovelace — Resume</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2933; margin: 0; padding: 32px; background: #f9fafb; }
        h1 { margin-bottom: 0; font-size: 28px; }
        h2 { font-size: 18px; border-bottom: 1px solid #d9e2ec; padding-bottom: 4px; text-transform: uppercase; letter-spacing: 1px; color: #102a43; }
        .contact { margin-top: 4px; color: #486581; }
        .headline { color: #243b53; margin-top: 8px; font-weight: 600; }
        section { margin-top: 24px; }
        ul { padding-left: 18px; }
        ul.skills { display: flex; flex-wrap: wrap; list-style: none; padding: 0; margin: 0; }
        ul.skills li { background: #e1effe; color: #1d4ed8; padding: 4px 8px; border-radius: 12px; margin: 4px 8px 4px 0; }
        li { margin-bottom: 12px; }
        li div { margin-top: 6px; color: #334e68; }
    </style>
</head>
<body>
    <header>
        <h1>Ada Lovelace</h1>
        <div class="contact"><span>ada@example.com</span> • <span>+1 555-1234</span> • <span>London</span></div>
        <p class="headline">Mathematician &amp; Writer</p>
    </header>
    <main>
        <section><h2>Summary</h2><p>First programmer &amp; visionary.<br>
Working on &quot;Analytical&quot; Engine.</p></section>
        <section><h2>Experience</h2><ul><li><strong>Lead Analyst</strong> at Babbage Engines <em>(1833-1842)</em><div>Developed algorithms<br>
Documented notes</div></li></ul></section>
        <section><h2>Skills</h2><ul class="skills"><li>Mathematics</li><li>Programming</li></ul></section>
    </main>
</body>
</html>
HTML;

assert($html === $expectedHtml, 'HTML builder should render the expected resume markup.');

$jsonBuilder = new JsonProfileBuilder();

$jsonData = [
    'name' => 'Grace Hopper',
    'title' => 'Computer Scientist',
    'email' => 'grace@example.com',
    'summary' => 'Collaborated across teams.',
    'experience' => [
        [
            'title' => 'Rear Admiral',
            'company' => 'US Navy',
            'period' => '1943-1986',
            'description' => 'Led computing efforts.',
        ],
        [
            'role' => 'Researcher',
            'description' => 'Created COBOL.',
        ],
    ],
    'skills' => ['Leadership', 'COBOL', '', 'Compilers', 'Teamwork', 'Innovation'],
];

$fullJson = $director->buildFullProfile($jsonBuilder, $jsonData);
$fullProfile = json_decode($fullJson, true, 512, JSON_THROW_ON_ERROR);

assert($fullProfile['name'] === 'Grace Hopper');
assert($fullProfile['headline'] === 'Computer Scientist');
assert($fullProfile['contacts'] === ['grace@example.com']);
assert(count($fullProfile['experience']) === 2);
assert($fullProfile['skills'] === ['Leadership', 'COBOL', 'Compilers', 'Teamwork', 'Innovation']);

$previewJson = $director->buildPreview($jsonBuilder, $jsonData);
$previewProfile = json_decode($previewJson, true, 512, JSON_THROW_ON_ERROR);

assert($previewProfile['name'] === 'Grace Hopper');
assert($previewProfile['headline'] === 'Computer Scientist');
assert($previewProfile['contacts'] === ['grace@example.com']);
assert(count($previewProfile['experience']) === 1);
assert($previewProfile['skills'] === ['Leadership', 'COBOL', 'Compilers', 'Teamwork', 'Innovation']);

echo "ProfileBuilder tests passed\n";
