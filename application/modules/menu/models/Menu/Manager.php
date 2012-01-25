<?php
/**
 * @see Core_Model_Manager
 */
require_once 'Core/Model/Manager.php';

/**
 * Menu_Model_Menu_Manager
 *
 * @category    Application
 * @package     Menu_Menu_Model_Menu
 * @subpackage  Manager
 *
 * @author      Valeriu Baleyko <baleyko.v.v@gmail.com>
 * @author      Alexander Khaylo <alex.khaylo@gmail.com>
 * @copyright   Copyright (c) 2011 NIX Solutions (http://www.nixsolutions.com)
 */
class Menu_Model_Menu_Manager extends Core_Model_Manager
{
    /**
     * Model table instance
     *
     * @var null|Menu_Model_Menu_Table
     */
    protected $_dbTable = null;

    /**
     * Model class name
     *
     * @var string
     */
    protected $_modelName = 'Menu_Model_Menu';

    /**
     * Array of menu tree
     *
     * @var null|array
     */
    protected $_menuArray = null;

    /**
     * Array for creating parent's menu in select
     */
    protected $_parentArray = array();

    /**
     * Default root directory with id = 0
     */
    protected $_rootDirectory = array(
        'label'   => null,
        'id'      => 0,
        'type'    => 'uri',
        'uri'   => '/',
        'visible' => true,
        'active' => false
    );
    /**
     * Menu tree
     *
     * @var null|Menu_Model_Menu
     */
    protected $_menuTableRowset = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDbTable(new Menu_Model_Menu_Table());
        $this->setMenuRowset();
    }

    /**
     * Get parent menu option for form selectbox
     *
     * @return mixed
     */
    public function getMenuItemsForEditForm()
    {
        $this->setMenuRowset();
        $array = array();
        foreach ($this->_menuTableRowset as $data) {
            $array[$data['parentId']][] =  $data;
        }
        $this->buildTreeGt($array, 0);
        //$this->buildTree($array, 0, 0, 1);
        return array_merge(array(0 => '/'), $this->_parentArray);
    }

    /**
     * Build hierarchical tree for menu of parents
     *
     * @param $array array
     * @param $parentId int
     * @param $level int
     * @param $shift int
     */
    public function buildTree($array, $parentId, $level, $shift)
    {
        if ($parentId == 0) {
            $this->_parentArray = array(0 => 'No parent');
        }
        if (is_array($array) && isset($array[$parentId]) && count($array[$parentId]) > 0) {
            foreach ($array[$parentId] as $cat) {
                $level = $level + $shift;
                $this->_parentArray[$cat['id']] = str_pad('', $level - $shift, "-", STR_PAD_RIGHT).$cat['label'];
                $this->buildTree($array, $cat['id'], $level, $shift);
                $level = $level - $shift;
            }
        }
    }


    /**
     * build tree Gt
     * Insert menu tree with ">" to $this->_parentArray
     * @param array $array
     * @param int $parentId
     */
    public function buildTreeGt($array, $parentId)
    {
        if ($parentId == 0) {
            $this->_parentArray = array();
        }
        if (is_array($array) && isset($array[$parentId]) && count($array[$parentId]) > 0) {
            foreach ($array[$parentId] as $cat) {
                if (isset($this->_parentArray[$parentId])) {
                    $this->_parentArray[$cat['id']] = $this->_parentArray[$parentId].' > '.$cat['label'];
                } else {
                    $this->_parentArray[$cat['id']] = $cat['label'];
                }
                $this->buildTreeGt($array, $cat['id']);
            }
        }
    }

    /**
     * get menu by id
     * @param int $id
     * @return array
     */
    public function getMenuById($id)
    {
        $menuArray = $this->getMenuArray();
        $arr = $this->getArrayItemByKey($menuArray, 'id', $id);
        return $arr;
    }

    /**
     * get menu by label
     * @param string $label
     * @return array
     */
    public function getMenuByLabel($label = null)
    {
        $menuArray = $this->getMenuArray();
        $arr = $this->getArrayItemByKey($menuArray, 'label', $label);
        return $arr;
    }

    /**
     * get array item by key
     * @param array $arr
     * @param string $name
     * @param string $value
     * @return array
     */
    public function getArrayItemByKey($arr, $name, $value)
    {
        if (!is_array($arr)) {
            return null;
        }

        if (isset($arr[$name]) && $arr[$name] == $value) {
            $arr['label'] = null;
            return $arr;
        }

        if (!isset($arr['pages'])) {
            return null;
        }

        foreach ($arr['pages'] as $page) {
            $results = $this->getArrayItemByKey($page, $name, $value);
            if (null !== $results) {
                return $results;
            }
        }
        return null;
    }

    /**
     * make parent child relations
     * @param array $inputArray
     * @return array
     */
    public function makeParentChildRelations($inputArray)
    {
        $inputArray = $this->isExistRoutes($inputArray);
        if (!is_array($inputArray)) {
            return null;
        }

        $outputArray = array();

        foreach ($inputArray as $entry) {
            $entry['pages'] = array();
            $id = $entry['id'];

            if (isset($entry['params']) && is_string($entry['params'])) {
                $entry['params'] = (array)json_decode($entry['params']);
                $entry['order']  = $entry['position'];
            }
            if ($entry['parentId'] == 0) {
                $all[$id] = $entry;
                $outputArray[] = &$all[$id];

            } else {
                $pid = $entry['parentId'];

                if (isset($all[$pid])) {
                    $all[$id] = $entry;
                    $all[$pid]['pages'][$entry['label']] =& $all[$id];
                }
            }
        }

        return $outputArray;
    }
    /**
     * Verification routes on exist
     * Enter description here ...
     * @param array $inputArray
     * @return array
     */
    public function isExistRoutes($inputArray)
    {
        if (!is_array($inputArray)) {
            return null;
        }
        $routes = $this->getNamesOfRoutes();
        $newArray = array();
        foreach ($inputArray as $name) {
            if ($name['type'] != Menu_Model_Menu::TYPE_MVC || isset($routes[$name['route']])) {
                $newArray[] = $name;
            }
        }
        return $newArray;
    }

    /**
     * set menu array
     */
    protected function setMenuArray()
    {
        if (null === $this->_menuArray) {
            if ($this->_menuTableRowset instanceof Zend_Db_Table_Rowset) {
                $array = $this->_menuTableRowset->toArray();
                $this->_menuArray = $this->makeParentChildRelations($array);
            } else {
                $this->_menuArray = array();
            }
        }
    }

    /**
     * get menu array
     * Return _menuArray
     * @return array
     */
    public function getMenuArray()
    {
        $this->setMenuArray();
        $menuArray = $this->_rootDirectory;
        $menuArray['pages'] = $this->_menuArray;
        return $menuArray;
    }


    /**
     * @return void
     */
    public function setMenuRowset()
    {
        if (null === $this->_menuTableRowset) {
            $this->_menuTableRowset = $this->_dbTable->fetchAll($this->_dbTable->select()->order('parentId ASC'));
        }
    }

    /**
     * get row by id
     *
     * @param $id int
     * @return db row
     */
    public function getRowById($id)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('id = ?', $id);
        $row = $this->getDbTable()->fetchRow($this->getDbTable()->select()->where($where));
        return $row;
    }

    /**
     * remove by id
     *
     * @param int $id
     * @return bool
     */
    public function removeById($id)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('id = ?', $id);
        if ($this->getDbTable()->delete($where)) {
            //update child if deleted parent
            $where = $this->getDbTable()->getAdapter()->quoteInto('parentId = ?', $id);
            $this->getDbTable()->update(array('parentId'  => 0), $where);
            return true;
        }
        return false;
    }

    /**
     * moveToById
     *
     * @param int $id
     * @param string $to
     * @return bool
     */

    public function moveToById($id, $to)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('id = ?', $id);
        $row = $this->getDbTable()->fetchRow($this->getDbTable()->select()->where($where));
        if (!isset($row->id)) {
            return false;
        }
        if ($to == 'down') {
            $result = $this->moveDownItem($row->parentId, $row->position);
        } else {
            $result = $this->moveUpItem($row->parentId, $row->position);
        }
        return $result;
    }

    /**
     * move up item
     *
     * @param int $parentId
     * @param int $position
     * @return bool
     */
    public function moveUpItem($parentId, $position)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('parentId = ?', $parentId);
        $where .= $this->getDbTable()->getAdapter()->quoteInto(' AND position <= ?', $position);
        $select = $this->getDbTable()->select()->where($where)->order('position DESC')->limit(2, 0);
        $row = $this->getDbTable()->fetchAll($select);

        if (count($row) == 2) {
            $where = $this->getDbTable()->getAdapter()->quoteInto('id = ?', $row[0]['id']);
            $result = $this->getDbTable()->update(array('position'  => $row[1]['position']), $where);
            if (!$result) {
                return false;
            }
            $where = $this->getDbTable()->getAdapter()->quoteInto('id = ?', $row[1]['id']);
            $result = $this->getDbTable()->update(array('position'  => $row[0]['position']), $where);
            if (!$result) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * move down item
     *
     * @param int $parentId
     * @param int $position
     * @return bool
     */
    public function moveDownItem($parentId, $position)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('parentId = ?', $parentId);
        $where .= $this->getDbTable()->getAdapter()->quoteInto(' AND position >= ?', $position);
        $select = $this->getDbTable()->select()->where($where)->order('position ASC')->limit(2, 0);
        $row = $this->getDbTable()->fetchAll($select);

        if (count($row) == 2) {
            $where = $this->getDbTable()->getAdapter()->quoteInto('id = ?', $row[0]['id']);
            $result = $this->getDbTable()->update(array('position'  => $row[1]['position']), $where);
            if (!$result) {
                return false;
            }
            $where = $this->getDbTable()->getAdapter()->quoteInto('id = ?', $row[1]['id']);
            $result = $this->getDbTable()->update(array('position'  => $row[0]['position']), $where);
            if (!$result) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * get last position by parent
     *
     * @param int $id
     * @return int
     */
    public function getLastPositionByParent($id)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('parentId = ?', $id);
        $select = $this->getDbTable()->select()->where($where)->order('position DESC');
        $row = $this->getDbTable()->fetchRow($select);
        return $row->position + 1;
    }

    /**
     * get names of routes
     *
     * @return array
     */
    public function getNamesOfRoutes()
    {
        $instance = Zend_Controller_Front::getInstance();
        $routes = $instance->getRouter()->getRoutes();

        $names = array();
        foreach ($routes as $routeName => $route) {
            if ($routes[$routeName] instanceof Zend_Controller_Router_Route_Static) {
                if (preg_match("/([\S]*)Default$/i", $routeName, $data) && isset($data[1])) {
                    $names[$data[1]] = $data[1];
                } else {
                    $names[$routeName] = $routeName;
                }
            } elseif ($routes[$routeName] instanceof Zend_Controller_Router_Route) {
                if (preg_match("/([\S]*)Default$/i", $routeName, $data) && isset($data[1])) {
                    $names[$data[1]] = $data[1];
                } else {
                    $names[$routeName] = $routeName;
                }
            } elseif ($routes[$routeName] instanceof Zend_Controller_Router_Route_Regex) {
                if (preg_match("/([\S]*)Default$/i", $routeName, $data) && isset($data[1])) {
                    //var_dump($data);exit;
                    $names[$data[1]] = $data[1];
                } else {
                    $names[$routeName] = $routeName;
                }
            } elseif ($routes[$routeName] instanceof Zend_Controller_Router_Route_Module) {
                if (preg_match("/([\S]*)Default$/i", $routeName, $data) && isset($data[1])) {
                    $names[$data[1]] = $data[1];
                } else {
                    $names[$routeName] = $routeName;
                }
            }
        }
        return $names;
    }

    /**
     * Get all routes
     *
     * @return array
     */
    public function getRoutes()
    {
        $instance = Zend_Controller_Front::getInstance();

        $routes = $instance->getRouter()->getRoutes();
        $routesArray = array();
        foreach ($routes as $routeName => $route) {
            if ($routes[$routeName] instanceof Zend_Controller_Router_Route_Static) {
                if (preg_match("/([\S]*)Default$/i", $routeName, $data) && isset($data[1])) {
                    $routeName = $data[1];
                }
                $routesArray[$routeName] = $this->getInfoFromStaticRoute($routeName, (array)$route);
            } elseif ($routes[$routeName] instanceof Zend_Controller_Router_Route) {
                if (preg_match("/([\S]*)Default$/i", $routeName, $data) && isset($data[1])) {
                    $routeName = $data[1];
                }
                $routesArray[$routeName] = $this->getInfoFromRoute($routeName, (array)$route);
            } elseif ($routes[$routeName] instanceof Zend_Controller_Router_Route_Regex) {
                if (preg_match("/([\S]*)Default$/i", $routeName, $data) && isset($data[1])) {
                    $routeName = $data[1];
                }
                $routesArray[$routeName] = $this->getInfoFromRegexRoute($routeName, (array)$route);
            } elseif ($routes[$routeName] instanceof Zend_Controller_Router_Route_Module) {
                if (preg_match("/([\S]*)Default$/i", $routeName, $data) && isset($data[1])) {
                    $routeName = $data[1];
                }
                $routesArray[$routeName] = $this->getInfoFromModuleRoute($routeName, (array)$route, $instance);
            } else {
                //
            }
        }
        return $routesArray;
    }

    /**
     * get info from static route
     *
     * @param string $routeName
     * @param array $route
     * @return array
     */
    public function getInfoFromStaticRoute($routeName, $route)
    {
        $defaults = array();
        foreach ($route as $names => $val) {
            if (preg_match("/_route/i", $names)) {
                $path = $val;
            }
            if (preg_match("/_defaults/i", $names)) {
                $defaults = $val;
            }
        }
        return array(
            'name'       => $routeName,
            'path'       => '/' . $path,
            'type'       => Menu_Model_Menu::ROUTE_TYPE_STATIC,
            'module'     => $defaults['module'],
            'controller' => $defaults['controller'],
            'action'     => $defaults['action']
        );
    }

    /**
     * get info from route
     *
     * @param string $routeName
     * @param array $route
     * @return array
     */
    public function getInfoFromRoute($routeName, $route)
    {
        $params = array();
        $parts = array();
        $arrayParams = array();
        $defaults = array();
        $path = '';
        $wildcard = false;
        foreach ($route as $names => $val) {
            if (preg_match("/_variables/i", $names)) {
                foreach ($val as $names => $param) {
                    $params[$names] = $param;
                    $arrayParams[$param] = $param;
                }
            }
            if (preg_match("/_part/i", $names)) {
                foreach ($val as $names => $part) {
                    $parts[$names] = $part;
                    if ($part == "*") {
                        $wildcard = true;
                    }
                }
            }
            if (preg_match("/_defaults/i", $names)) {
                $defaults = $val;
            }
        }

        foreach ($parts as $names => $part) {
            if (isset($params[$names])) {
                $path .= $part . "/:" . $params[$names];
            } else {
                $path .= "/" . $part;
            }
        }
        return array(
            'name'     => $routeName,
            'path'     => $path,
            'type'     => Menu_Model_Menu::ROUTE_TYPE_ROUTE,
            'wildcard' => $wildcard,
            'params'   => $arrayParams,
            'module'     => $defaults['module'],
            'controller' => $defaults['controller'],
            'action'     => $defaults['action']
        );
    }

    /**
     * get info from regex route
     *
     * @param string $routeName
     * @param array $route
     * @return array
     */
    public function getInfoFromRegexRoute($routeName, $route)
    {
        $params = array();
        $defaults = array();
        $regex = '';
        $pattern = "/\([\S\s]*\)/i";
        foreach ($route as $names => $val) {

            if (preg_match("/_regex/i", $names)) {
                $regex = $val;
            }
            if (preg_match("/_map/i", $names)) {
                $num = 0;
                foreach ($val as $param => $val) {
                    $params[$param] = $param;
                    $regex =  preg_replace($pattern, $param, $regex, 1);
                    $num++;
                }
            }
            if (preg_match("/_defaults/i", $names)) {
                $defaults = $val;
            }
        }
        $path = str_replace("\\", "", $regex);
        return array(
            'name'   => $routeName,
            'path'   => '/' . $path,
            'type'   => Menu_Model_Menu::ROUTE_TYPE_REGEX,
            'params' => $params,
            'module'     => $defaults['module'],
            'controller' => $defaults['controller'],
            'action'     => $defaults['action']
        );
    }

    /**
     * get info from module route
     *
     * @param string $routeName
     * @param array $route
     * @param Zend_Controller_Front $instance
     * @return array
     */
    public function getInfoFromModuleRoute($routeName, $route, $instance)
    {
        $modules = $instance->getControllerDirectory();
        $module = array();
        foreach ($modules as $modul => $path) {
            if (is_dir($path)) {
                $controllers = scandir($path);
                foreach ($controllers as $key => $name) {
                    if (preg_match("/^([\w]*)Controller.php$/", $name, $matches)) {
                        $lowerClasses = strtolower($matches[1]);
                        $module[$modul][$lowerClasses] = $lowerClasses;
                    }
                }
            }

        }
        return array(
            'name'   => $routeName,
            'path'   => '/:module/:controller/:action',
            'type'   => Menu_Model_Menu::ROUTE_TYPE_MODULE,
            'modules' => $module
        );
    }

    /**Check target
     *
     * @param $target string
     * @return string|null
     */
    protected function _checkTarget($target)
    {
        if ($target == Menu_Model_Menu::TARGET_BLANK ||
            $target == Menu_Model_Menu::TARGET_PARENT ||
            $target == Menu_Model_Menu::TARGET_SELF ||
            $target == Menu_Model_Menu::TARGET_TOP) {

            return $target;
        } else {
            return null;
        }
    }


    /**
     * insert menu item in db
     *
     * @param array $data
     * @return row|bool
     */
    public function addMenuItem($data = null)
    {
        if (!empty($data) && is_array($data)) {

            $menu = $this->getDbTable()->createRow();
            $options = array();

            if (isset($data['params']) && is_array($data['params'])) {
                foreach ($data['params'] as $param => $value) {
                    if ($value) {
                        $options[$param] = $value;
                    }
                }
            }
            $menu->visible = (int)$data['visible'];
            if ($data['class']) {
                $menu->class = $data['class'];
            }

            $menu->target = $this->_checkTarget($data['target']);

            if ($data['linkType'] == Menu_Model_Menu::TYPE_URI) {//link
                $menu->type = Menu_Model_Menu::TYPE_URI;
                if (!$data['uri']) {
                    return false;
                }
                $menu->uri = $data['uri'];


            } elseif ($data['linkType'] == Menu_Model_Menu::TYPE_MVC) {//route
                $menu->type = Menu_Model_Menu::TYPE_MVC;
                if (!$data['route']) {
                    return false;
                }
                $routes = $this->getRoutes();

                if (isset($routes[$data['route']]) == false) {
                    return false;
                }

                $menu->routeType = $routes[$data['route']]['type'];

                if ($menu->routeType != Menu_Model_Menu::ROUTE_TYPE_MODULE) {
                    $menu->module     = $routes[$data['route']]['module'];
                    $menu->controller = $routes[$data['route']]['controller'];
                    $menu->action     = $routes[$data['route']]['action'];
                } else {
                    $menu->module     = $options['module'];
                    $menu->controller = $options['controller'];
                    $menu->action     = $options['action'];
                    unset($options['module']);
                    unset($options['controller']);
                    unset($options['action']);
                }

                $menu->route = $routes[$data['route']]['name'];
            }

            $menu->label    = $data['label'];
            $menu->title    = $data['title'];
            $menu->params   = json_encode($options);
            $menu->parentId = $data['parent'];
            $menu->position = $this->getLastPositionByParent($data['parent']);

            if ($menu->save()) {
                return $menu;
            }
        }
        return false;
    }


    /**
     * Update menu item
     *
     * @param array $data
     * @return row|bool
     */
    public function updateMenuItem($data = null)
    {
        if (!empty($data) && is_array($data)) {
            $menu = $this->getRowById($data['id']);

            if ($menu->id) {

                $options = array();

                if (isset($data['params']) && is_array($data['params'])) {
                    foreach ($data['params'] as $param => $value) {
                        if ($value) {
                            $options[$param] = $value;
                        }
                    }
                }
                $menu->visible = (int)$data['visible'];
                if ($data['class']) {
                    $menu->class = $data['class'];
                }

                $menu->target = $this->_checkTarget($data['target']);

                if ($data['linkType'] == Menu_Model_Menu::TYPE_URI) {//link
                    $menu->type = Menu_Model_Menu::TYPE_URI;
                    if (!$data['uri']) {
                        return false;
                    }
                    $menu->uri = $data['uri'];


                } elseif ($data['linkType'] == Menu_Model_Menu::TYPE_MVC) {//route
                    $menu->type = Menu_Model_Menu::TYPE_MVC;
                    if (!$data['route']) {
                        return false;
                    }
                    $routes = $this->getRoutes();
                    if (!isset($routes[$data['route']])) {
                        return false;
                    }

                    $menu->routeType = $routes[$data['route']]['type'];

                    if ($menu->routeType != Menu_Model_Menu::ROUTE_TYPE_MODULE) {
                        $menu->module     = $routes[$data['route']]['module'];
                        $menu->controller = $routes[$data['route']]['controller'];
                        $menu->action     = $routes[$data['route']]['action'];
                    } else {
                        $menu->module     = $options['module'];
                        $menu->controller = $options['controller'];
                        $menu->action     = $options['action'];
                        //clean
                        unset($options['module']);
                        unset($options['controller']);
                        unset($options['action']);
                    }

                    $menu->route = $routes[$data['route']]['name'];
                }

                $menu->label     = $data['label'];
                $menu->title     = $data['title'];
                $menu->params    = json_encode($options);

                if ($menu->parentId != $data['parent']) {
                    $menu->parentId = $data['parent'];
                    $menu->position  = $this->getLastPositionByParent($data['parent']);
                }

                if ($menu->save()) {
                    return $menu;
                }
            }
        }
        return false;
    }
}
