#!/usr/bin/env php
<?php
$shortopts = "a"; // These options do not accept values

$longopts  = array(
    "regex::",    // Optional value
    "append",     // No value
);

global $options;
$options = getopt($shortopts, $longopts);
// var_dump($options);
// exit;

function getOption($opt,$type='short')
{
  global $options;

  switch ($type) {
    case 'short':
      if ( isset($options[$opt]) ) {
        return true;
      }
      break;
    case 'long':
      if ( isset($options[$opt]) && false === $options[$opt] ) {
        return true;
      }
      else if ( isset($options[$opt]) ) {
        return $options[$opt];
      }
      break;
  }
}

if (!defined('DS')) {
  define('DS', DIRECTORY_SEPARATOR);
}

global $regex;
$regex = getOption('regex', 'long');
if ( empty($regex) ) {
  $regex = '->addError';
}

require_once "vendor/autoload.php";

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

global $results;
$results=array(array('filename', 'line', 'exception'));

function process($dir)
{
  echo "\nprocessing $dir";
  global $results;
  global $filter;
  global $root_dir;
  global $regex;

  // Recurse into dirs

  $finder = new Finder();
  $finder
    ->directories()
    ->in($dir)
    ->filter(function (\SplFileInfo $file)
      {
        if ( preg_match('/vendor/i', $file->getRealPath()) ) {
          return false;
        }
      })
  ;

  foreach ($finder as $dirs) {
    if ( preg_match('/vendor/i', $dirs->getRealPath()) ) continue;

    process($dirs->getRealPath());
  }

  // process directory files
  $finder = new Finder();
  $finder
    ->files()
    ->name('*.php')
    ->contains($regex)
    ->in($dir);

  foreach ($finder as $file) {
    if ( preg_match('/vendor/i', $file->getRealPath()) ) continue;

    $contents = $file->getContents();
    $contents = preg_split('/\n/', $contents);

    $line_number=0;
    foreach ($contents as $line) {
      if ( preg_match('/'.$regex.'/', $line) ) {
        $filename = str_replace($root_dir, '', $file->getRealPath());

        $idx = slug(str_replace('/', '-', $filename) . '-' . $line);

        $results[$idx] = array(
          $filename,
          $line_number,
          trim($line)
        );
      }
      $line_number++;
    }
  }

}

function slug( $text )
{
  // texto a minusculas
  $slug = strtolower($text);

  // Reemplazo de caracteres no seguros
  $filtros = array('/\s/','/á/','/é/','/í/','/ó/','/ú/','/ñ/');
  $seguros = array('-','a','e','i','o','u','n');
  $slug = preg_replace($filtros, $seguros, $slug);

  // Saco caracteres no ascii
  $filtros = array('/[^-a-z0-9]/','/--+/');
  $seguros = array('','-');
  $slug = preg_replace($filtros, $seguros, $slug);

  $slug = trim($slug, '-');

  return $slug;
}

function writeCSV($rows,$result_filename) {
  $mode = 'w';
  if ( getOption('a') || getOption('append', 'long') ) {
    $mode = 'a';
  }
  $field_delimeter = ",";
  $field_encloser  = '"';
  $row_delimeter   = "\n";

  $csv_content = '';
  foreach ($rows as $row) {
    $csv_row = $field_encloser
             . implode($field_encloser.$field_delimeter.$field_encloser, $row)
             . $field_encloser
             . $row_delimeter;

    $csv_content .= $csv_row;
  }

  $fp = fopen($result_filename, $mode);
  fwrite($fp, $csv_content);
  fclose($fp);
}

echo "\n";

global $root_dir;
$root_dir = '/home/workspace/Workspace/htdocs/DemandMedia/s6-app';

$directories = array('/home/workspace/Workspace/htdocs/DemandMedia/s6-app');
foreach ($directories as $directory) {
  process($directory);
}

writeCSV($results,__DIR__.DS.'error_list.csv');

echo "\n\ndone\n\n";