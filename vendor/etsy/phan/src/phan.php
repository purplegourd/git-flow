<?php declare(strict_types=1);

// Phan does a ton of GC and this offers a major speed
// improvment if your system can handle it (which it
// should be able to)
gc_disable();

// Check the environment to make sure Phan can run successfully
require_once(__DIR__ . '/requirements.php');

// Build a code base based on PHP internally defined
// functions, methods and classes before loading our
// own
$code_base = require_once(__DIR__ . '/codebase.php');

require_once(__DIR__ . '/Phan/Bootstrap.php');

use Phan\CLI;
use Phan\CodeBase;
use Phan\Config;
use Phan\Phan;

// Create our CLI interface and load arguments
$cli = new CLI();

$file_list = $cli->getFileList();

// If requested, expand the file list to a set of
// all files that should be re-analyzed
if (Config::get()->expand_file_list) {
    assert(
        (bool)(Config::get()->stored_state_file_path),
        'Requesting an expanded dependency list can only '
        . ' be done if a state-file is defined'
    );

    // Analyze the file list provided via the CLI
    $file_list = Phan::expandedFileList($code_base, $file_list);
}

// Analyze the file list provided via the CLI
$is_issue_found =
    Phan::analyzeFileList(
        $code_base,
        $file_list
    );

// Provide an exit status code based on if
// issues were found
exit($is_issue_found ? EXIT_ISSUES_FOUND : EXIT_SUCCESS);
