<?php declare(strict_types = 1);
namespace Phan\Output\Printer;

use Phan\Issue;
use Phan\IssueInstance;
use Phan\Output\IssuePrinterInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PylintPrinter implements IssuePrinterInterface
{
    /** @var OutputInterface */
    private $output;

    /** @param IssueInstance $instance */
    public function print(IssueInstance $instance)
    {
        $message = sprintf(
            "%s: %s",
            $instance->getIssue()->getType(),
            $instance->getMessage()
        );
        $line = sprintf("%s:%d: [%s] %s",
            $instance->getFile(),
            $instance->getLine(),
            self::get_severity_code($instance),
            $message
        );

        $this->output->writeln($line);
    }

    public static function get_severity_code(IssueInstance $instance) : string
    {
        $issue = $instance->getIssue();
        $categoryId = $issue->getTypeId();
        switch($issue->getSeverity()) {
        case Issue::SEVERITY_LOW:
            return 'C' . $categoryId;
        case Issue::SEVERITY_NORMAL:
            return 'W' . $categoryId;
        case Issue::SEVERITY_CRITICAL:
            return 'E' . $categoryId;
        }
    }

    /**
     * @param OutputInterface $output
     */
    public function configureOutput(OutputInterface $output)
    {
        $this->output = $output;
    }
}
