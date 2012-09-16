<?php
namespace Cron\Exception;

use Cron\ExceptionInterface;

/**
 * RuntimeException
 *
 * @author heartsentwined <heartsentwined@cogito-lab.com>
 * @license GPL http://opensource.org/licenses/gpl-license.php
 */
class RuntimeException
    extends \RuntimeException
    implements ExceptionInterface
{
}
