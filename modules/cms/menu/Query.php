<?php

namespace cms\menu;

use Yii;
use Exception;
use cms\menu\Iterator;

/**
 * Menu Query Builder
 * 
 * Ability to create menu querys to find your specifc menu items.
 * 
 * Basic where conditions:
 * 
 * ```php
 * $data = (new \cms\menu\Query())->where(['parent_nav_id' => 0])->all();
 * ```
 * 
 * By default the Menu Query will get the default language, or the current active language. To force
 * a specific language use the `lang()` method in your query chain:
 * 
 * ```php
 * $data = (new \cms\menu\Query())->where(['parent_nav_id' => 0])->lang('en')->all();
 * ```
 * 
 * @since 1.0.0-beta1
 * @author nadar
 */
class Query extends \yii\base\Object
{
    private $_where = [];
    
    private $_lang = null;
    
    private $_menu = null;
    
    private $_whereOperators = ['<', '<=', '>', '>=', '=', '=='];
    
    public function setMenu(\cms\components\Menu $menu)
    {
        $this->_menu = $menu;
    }
    
    public function getMenu()
    {
        if ($this->_menu === null) {
            $this->_menu = Yii::$app->get('newmenu'); // @todo rename to menu on release
        }
        
        return $this->_menu;
    }
    
    /**
     * Query where similar behavior of filtering items
     * 
     * ### Operator Filtering
     * 
     * ```php
     * where(['operator', 'field', 'value']);
     * ```
     * 
     * Allowed operators
     * + < *expression where field is smaller then value*
     * + > *expression where field is bigger then value*
     * + = *expression where field is equal value*
     * + <= *expression where field is small or equal then value*
     * + >= *expression where field is bigger or equal then value*
     * + == *expression where field is equal to the value and even the type must be equal*
     * 
     * Only one operator speific argument can be provided, to chain another expression
     * use the `andWhere()` method.
     * 
     * ### Multi Dimension Filtering
     * 
     * The most common case for filtering items is the equal expression combined with
     * add statements.
     * 
     * For example the following expression
     * 
     * ```php
     * where(['=', 'parent_nav_id', 0])->andWhere(['=', 'cat', 'footer']);
     * ```
     * 
     * is equal to the short form multi deimnsion filtering expression
     * 
     * ```php
     * where(['parent_nav_id' => 0, 'cat' => 'footer']);
     * ```
     * 
     * @param array $args
     * @return \cms\menu\Query
     */
    public function where(array $args)
    {
        foreach($args as $key => $value) {
            if (in_array($value, $this->_whereOperators)) {
                if (count($args) !== 3) {
                    throw new Exception("Wrong where expression, see http://luya.io/api/cms-menu-query.html#where()-detail");
                }
                $this->_where[] = ['op' => $args[0], 'field' => $args[1], 'value' => $args[2]];
                break;
            } else {
                $this->_where[] = ['op' => '=', 'field' => $key, 'value' => $value];
            }
        }
        
        return $this;
    }
    
    public function andWhere(array $args)
    {
        return $this->where($args);
    }
    
    /**
     * Changeing the container in where the data should be collection, by default the composition
     * `langShortCode` is the default language code. This represents the current active language,
     * or the default language if no information is presented.
     * 
     * @param string $langShortCode Language Short Code e.g. de or en
     * @return \cms\menu\Query
     */
    public function lang($langShortCode)
    {
        $this->_lang = $langShortCode;
        return $this;
    }
    
    public function getLang()
    {
        if ($this->_lang === null) {
            $this->_lang = $this->menu->composition['langShortCode'];
        }
        
        return $this->_lang;
    }
    
    public function arrayFilter($value, $field)
    {
        foreach($this->_where as $expression) {
            if ($expression['field'] == $field) {
                switch($expression['op']) {
                    case "=":
                        return ($value == $expression['value']);
                    case "==":
                        return ($value === $expression['value']);
                    case ">":
                        return ($value > $expression['value']);
                    case ">=":
                        return ($value >= $expression['value']);
                    case "<":
                        return ($value < $expression['value']);
                    case "<=":
                        return ($value <= $expression['value']);
                }
            }
        }
        return true;
    }
    
    public function filter(array $whereExpression, $containerData)
    {
        return array_filter($containerData, function($item) {
            foreach($item as $field => $value) {
                if (!$this->arrayFilter($value, $field)) {
                    return false;
                }
            }
            return true;
        });   
    }
    
    public function one()
    {
        $data = $this->filter($this->_where, $this->menu->languageContainerData($this->lang));
        
        if (count($data) == 0) {
            throw new Exception("Could not find an element for this where conditions.");
        }
        
        return static::createItemObject(array_values($data)[0]);
    }
    
    public function all()
    {
        return static::createArrayIterator($this->filter($this->_where, $this->menu->languageContainerData($this->lang)));
    }
    
    public static function createArrayIterator($data)
    {
       return Yii::createObject(['class' => Iterator::className(), 'data' => $data]);
    }
    
    public static function createItemObject(array $itemArray)
    {
        return Yii::createObject(['class' => Item::className(), 'itemArray' => $itemArray]);
    }
}