<?php
namespace Cron\Controller;

use Heartsentwined\BackgroundExec\BackgroundExec;
use Zend\Console\Request as ConsoleRequest;
use Zend\Mvc\Controller\AbstractActionController;

/**
 * Cron controller
 *
 * @author heartsentwined <heartsentwined@cogito-lab.com>
 * @license GPL http://opensource.org/licenses/gpl-license.php
 */
class CronController extends AbstractActionController
{
    /**
     * run the cron service
     *
     * if called from browser,
     * will suppress output and continue execution in background
     *
     * @return Response|void
     */
    public function indexAction()
    {
        if (!$this->getRequest() instanceof ConsoleRequest){
            BackgroundExec::start();
        }
        $sm     = $this->getServiceLocator();
        $cron   = $sm->get('cron');
        $em     = $sm->get('doctrine.entitymanager.orm_default');
        $cron
            ->setEm($em)
            ->run();

        if ($response = $this->getResponse()) {
            $response->setStatusCode(200);
            return $response;
        }
    }
}
