<?php declare(strict_types = 1);
namespace Phan\Tests;

use Phan\CodeBase;
use Phan\Output\Collector\BufferingCollector;
use Phan\Output\Printer\PlainTextPrinter;
use Phan\Phan;
use Symfony\Component\Console\Output\BufferedOutput;

abstract class AbstractPhanFileTest
    extends \PHPUnit_Framework_TestCase
    implements CodeBaseAwareTestInterface
{
    const EXPECTED_SUFFIX = '.expected';

    private $code_base;

    public function setCodeBase(CodeBase $code_base = null) {
        $this->code_base = $code_base;
    }

    /**
     * @return string[][] Array of <filename => [filename]>
     */
    abstract public function getTestFiles();

    /**
     * Placeholder for getTestFiles dataProvider
     *
     * @param string $sourceDir
     * @return string[][]
     */
    protected function scanSourceFilesDir($sourceDir, $expectedDir) {
        $files = array_filter(
            array_filter(
                scandir($sourceDir),
                function ($filename) {
                    return !in_array($filename, ['.', '..'], true);
                }
            )
        );

        return array_combine(
            $files, array_map(
                function ($filename) use ($sourceDir, $expectedDir) {
                    return [
                        [$sourceDir . DIRECTORY_SEPARATOR . $filename],
                        $expectedDir . DIRECTORY_SEPARATOR . $filename . self::EXPECTED_SUFFIX
                    ];
                }, $files
            )
        );
    }

    /**
     * This reads all files in `tests/files/src`, runs
     * the analyzer on each and compares the output
     * to the files's counterpart in
     * `tests/files/expected`
     *
     * @param string[] $test_file_list
     * @param string $expected_file_path
     * @dataProvider getTestFiles
     */
    public function testFiles($test_file_list, $expected_file_path) {
        $expected_output = '';
        if (is_file($expected_file_path)) {
            // Read the expected output
            $expected_output =
                trim(file_get_contents($expected_file_path));
        }

        $stream = new BufferedOutput();
        $printer = new PlainTextPrinter();
        $printer->configureOutput($stream);

        Phan::setPrinter($printer);
        Phan::setIssueCollector(new BufferingCollector());

        Phan::analyzeFileList($this->code_base, $test_file_list);

        $output = $stream->fetch();

        // Uncomment to save the output back to the expected
        // output. This should be done for error message
        // text changes and only if you promise to be careful.
        /*
        $saved_output = $output;
        $test_file_elements= explode('/', $test_file_list[0]);
        $test_file_name = array_pop($test_file_elements);
        $saved_output = preg_replace('/[^ :\n]*\/' . $test_file_name . '/', '%s', $saved_output);
        $saved_output = preg_replace('/closure_[^\(]*\(/', 'closure_%s(', $saved_output);
        if (!empty($saved_output) && strlen($saved_output) > 0) {
            $saved_output .= "\n";
        }
        file_put_contents($expected_file_path, $saved_output);
        $expected_output =
            trim(file_get_contents($expected_file_path));
        */

        $wanted_re = preg_replace('/\r\n/', "\n", $expected_output);
        // do preg_quote, but miss out any %r delimited sections
        $temp = "";
        $r = "%r";
        $startOffset = 0;
        $length = strlen($wanted_re);
        while($startOffset < $length) {
            $start = strpos($wanted_re, $r, $startOffset);
            if ($start !== false) {
                // we have found a start tag
                $end = strpos($wanted_re, $r, $start+2);
                if ($end === false) {
                    // unbalanced tag, ignore it.
                    $end = $start = $length;
                }
            } else {
                // no more %r sections
                $start = $end = $length;
            }
            // quote a non re portion of the string
            $temp = $temp . preg_quote(substr($wanted_re, $startOffset, ($start - $startOffset)),  '/');
            // add the re unquoted.
            if ($end > $start) {
                $temp = $temp . '(' . substr($wanted_re, $start+2, ($end - $start-2)). ')';
            }
            $startOffset = $end + 2;
        }
        $wanted_re = $temp;
        $wanted_re = str_replace(['%binary_string_optional%'], 'string', $wanted_re);
        $wanted_re = str_replace(['%unicode_string_optional%'], 'string', $wanted_re);
        $wanted_re = str_replace(['%unicode\|string%', '%string\|unicode%'], 'string', $wanted_re);
        $wanted_re = str_replace(['%u\|b%', '%b\|u%'], '', $wanted_re);
        // Stick to basics
        $wanted_re = str_replace('%e', '\\' . DIRECTORY_SEPARATOR, $wanted_re);
        $wanted_re = str_replace('%s', '[^\r\n]+', $wanted_re);
        $wanted_re = str_replace('%S', '[^\r\n]*', $wanted_re);
        $wanted_re = str_replace('%a', '.+', $wanted_re);
        $wanted_re = str_replace('%A', '.*', $wanted_re);
        $wanted_re = str_replace('%w', '\s*', $wanted_re);
        $wanted_re = str_replace('%i', '[+-]?\d+', $wanted_re);
        $wanted_re = str_replace('%d', '\d+', $wanted_re);
        $wanted_re = str_replace('%x', '[0-9a-fA-F]+', $wanted_re);
        $wanted_re = str_replace('%f', '[+-]?\.?\d+\.?\d*(?:[Ee][+-]?\d+)?', $wanted_re);
        $wanted_re = str_replace('%c', '.', $wanted_re);
        // %f allows two points "-.0.0" but that is the best *simple* expression

        $this->assertRegExp("/^$wanted_re\$/", $output,
            "Unexpected output in {$test_file_list[0]}");
    }
}
