<?php
/**
 * Controller plugin intended to set additional routes rules from standard
 * config file and files from modules /configs directories
 *
 * !!!ATTENTION
 *
 * As chains have to be after all the routes declarations, the application
 * config file (which better to use for chains) plugged in as last file.
 * Otherwise specify chains in the corresponding module config files
 * after the routes intended for chain have been declarated.
 *
 *
 * @uses       Zend_Controller_Plugin_Abstract
 *
 * @category   Core
 * @package    Core_Controller
 * @subpackage Plugins
 *
 * @author MYem (max.yemets@gmail.com)
 */
class Core_Application_Resource_Router
    extends Zend_Application_Resource_Router
{
    /**
     * Plugin configuration settings array
     *
     * @var array
     */
    protected $_config = 'routes';

    /**
     * Defined by Zend_Application_Resource_Resource
     *
     * @return Zend_Controller_Router_Rewrite
     */
    public function init()
    {
        if (null === $this->_router) {
            $router = $this->getRouter(); // returns $this->_router
            $router->addConfig($this->_getConfig());

            // add locale chain if using translate
            if ($this->getBootstrap()->hasPluginResource('Translate')) {
                $locale = new Zend_Controller_Router_Route(
                    ':locale',
                    array(),
                    array('locale' => '^[a-z]{2}$')
                );

                $router->addDefaultRoutes();

                foreach ($router->getRoutes() as $name => $route) {
                    //rename existing routes
                    $router->removeRoute($name)
                           ->addRoute($name . 'Default', $route)
                           //add chained routes
                           ->addRoute($name, $locale->chain($route));
                }
            }
        }
        return $this->_router;
    }

    /**
     * get config
     *
     * @return array
     */
    protected function _getConfig()
    {
        $bootstrap = $this->getBootstrap();

        $cache = false;
        if (!empty($this->_options['cache'])) {
            if ($bootstrap->hasPluginResource('CacheManager')) {
                $manager = $bootstrap->bootstrap('CacheManager')
                                     ->getResource('CacheManager');

                $cache = $manager->getCache($this->_options['cache']);
            }
        }

        $config = empty($this->_options['config']) ? $this->_config : $this->_options['config'];

        return new Zend_Config(
            Core_Module_Config::getConfig(
                $config,
                null,
                Core_Module_Config::MAIN_ORDER_LAST,
                $cache
            )
        );
    }
}
