<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$routes=app('router')->getRoutes()->getRoutesByName();
$report=['missing_literal_routes'=>[],'placeholder_links'=>[],'post_forms_without_local_csrf'=>[],'inline_style_files'=>[],'inline_script_files'=>[],'duplicate_extends'=>[]];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
    if(!$file->isFile() || !str_ends_with($file->getFilename(),'.blade.php'))continue;
    $text=file_get_contents($file->getPathname());$name=str_replace(base_path().DIRECTORY_SEPARATOR,'',$file->getPathname());
    preg_match_all('/(?<!->)\broute\(\s*[\'"]([a-zA-Z0-9_.-]+)[\'"]\s*[,)]/',$text,$matches);
    foreach(array_unique($matches[1]) as $route)if(!isset($routes[$route]))$report['missing_literal_routes'][]=[$name,$route];
    if(preg_match('/href\s*=\s*[\'"](?:#|javascript:void\(0\))[\'"]/',$text))$report['placeholder_links'][]=$name;
    preg_match_all('/<form\b[^>]*method=[\'"]POST[\'"][^>]*>(.*?)<\/form>/is',$text,$forms);
    foreach($forms[1] as $form)if(!str_contains($form,'@csrf'))$report['post_forms_without_local_csrf'][]=$name;
    if(str_contains($text,'<style'))$report['inline_style_files'][]=$name;
    if(preg_match('/<script(?![^>]*\bsrc=)[^>]*>/',$text))$report['inline_script_files'][]=$name;
    if(substr_count($text,'@extends(')>1)$report['duplicate_extends'][]=$name;
}
file_put_contents(storage_path('app/final-ui-static-audit.json'),json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
foreach($report as $key=>$values)echo $key.': '.count($values).PHP_EOL;
echo json_encode(array_diff_key($report,array_flip(['inline_style_files','inline_script_files'])),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
