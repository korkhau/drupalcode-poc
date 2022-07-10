<?php

require "vendor/autoload.php";
use PHPHtmlParser\Dom;
use League\Csv\Writer;

// @todo: add retrieving all results
$results = [];
$user_name = 'xjm';

$ch = curl_init();
// Let's use xjm user for reference.
curl_setopt($ch, CURLOPT_URL, 'https://git.drupalcode.org/users/' . $user_name . '/activity?limit=100&offset=1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json, text/plain, */*',
    'User-Agent: Postman',
    'X-Requested-With: XMLHttpRequest'
]);

$results[] = $decoded = json_decode(curl_exec($ch));

// @todo: add checking for all results.
// print_r('count: ' . $decoded->count . PHP_EOL);

curl_close($ch);

$dom = new Dom;
$csv = Writer::createFromFileObject(new SplTempFileObject());
$csv->setNewline("\r\n");
$csv->insertOne(["user", "time", "event_type", "event_target_type", "project_id", "project"]);

foreach ($results as $result) {
    $dom->loadStr($result->html);
    $events = $dom->find('div.event-item');
    foreach ($events as $event) {
        try {
            $target_type = $event->find('div.event-title')->find('span.event-target-type')->text;
        } catch (Exception $e) {
            $target_type = '';
        }

        $project_name = $event->find('div.event-title')->find('span.event-scope')->find('a')->title;

        $project_ch = curl_init();
        curl_setopt($project_ch, CURLOPT_URL, 'https://git.drupalcode.org/project/' . $project_name);
        curl_setopt($project_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($project_ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json, text/plain, */*',
            'User-Agent: Postman',
            'X-Requested-With: XMLHttpRequest'
        ]);

        $project_html = curl_exec($project_ch);

        // Handles forks.
        if(curl_getinfo($project_ch, CURLINFO_HTTP_CODE) !== 200) {
            curl_close($project_ch);

            $fork_ch = curl_init();
            curl_setopt($fork_ch, CURLOPT_URL, 'https://git.drupalcode.org/issue/' . $project_name);
            curl_setopt($fork_ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($fork_ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json, text/plain, */*',
                'User-Agent: Postman',
                'X-Requested-With: XMLHttpRequest'
            ]);

            $project_html = curl_exec($fork_ch);
            curl_close($fork_ch);
        }


        $project_dom = new Dom;
        $project_dom->loadStr($project_html);

        $csv->insertOne([
            $user_name,
            $event->find('time')->text,
            $event->find('div.event-title')->find('span.event-type')->text,
            $target_type,
            $project_dom->find('body')->getAttribute('data-project-id'),
            $project_name
        ]);
    }
}
$file = fopen("stats.csv", "w");
fwrite($file, $csv->toString());
fclose($file);
