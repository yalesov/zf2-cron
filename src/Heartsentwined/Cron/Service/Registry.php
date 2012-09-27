<?php
namespace Heartsentwined\Cron\Service;

use Heartsentwined\ArgValidator\ArgValidator;

/**
 * registry for all cron jobs
 *
 * @author heartsentwined <heartsentwined@cogito-lab.com>
 * @license GPL http://opensource.org/licenses/gpl-license.php
 */
class Registry
{
    /**
     * singleton
     */
    private static $instance = null;
    private function __construct() {}
    public static function getInstance()
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * clear the singleton
     *
     * @return void
     */
    public static function destroy()
    {
        self::$instance = null;
    }

    /**
     * the actual cron registry
     *
     * @var array
     */
    protected $cronRegistry = array();
    public static function setCronRegistry(array $cronRegistry)
    {
        $instance = self::getInstance();
        $instance->cronRegistry = $cronRegistry;

        return $instance;
    }
    public static function getCronRegistry()
    {
        $instance = self::getInstance();

        return $instance->cronRegistry;
    }

    /**
     * register a cron job
     *
     * @see Cron::trySchedule() for allowed cron expression syntax
     *
     * @param  string   $code      the cron job code / identifier
     * @param  string   $frequency cron expression
     * @param  callable $callback  the actual cron job
     * @param  array    $args      args to the cron job
     * @return self
     */
    public static function register(
        $code, $frequency, $callback, array $args = array())
    {
        ArgValidator::assert($code, array('string', 'min' => 1));
        ArgValidator::assert($callback, 'callable');
        /*
         * validation of $frequency (cron expression):
         * will be done together when scheduling
         * (errors thrown if invalid cron expression)
         */

        $instance = self::getInstance();
        $cronRegistry = $instance->getCronRegistry();

        $cronRegistry[$code] = array(
            'frequency' => $frequency,
            'callback'  => $callback,
            'args'      => $args,
        );
        $instance->setCronRegistry($cronRegistry);

        return $instance;
    }
}
