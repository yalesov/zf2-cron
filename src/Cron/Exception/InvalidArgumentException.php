<?php
namespace Cron\Exception;

use Cron\ExceptionInterface;

/**
 * InvalidArgumentException
 *
 * @author heartsentwined <heartsentwined@cogito-lab.com>
 * @license GPL http://opensource.org/licenses/gpl-license.php
 */
class InvalidArgumentException
    extends \InvalidArgumentException
    implements ExceptionInterface
{
}
