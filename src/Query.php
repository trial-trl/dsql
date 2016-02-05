<?php

namespace atk4\dsql;

class Query
{
    /**
     * Define templates for the basic queries
     */
    public $templates=[
        'select'=>"select [field] [from] [table]"
    ];

    /**
     * Hash containing configuration accumulated by calling methods
     * such as field(), table(), etc
     */
    private $args=[];

    /** If no fields are defined, this field is used */
    public $defaultField='*';

    /** Backtics are added around all fields. Set this to blank string to avoid */
    public $escapeChar='`';

    /**
     * Specifying options to constructors will override default
     * attribute values in this class
     *
     * @param array $options will initialize class properties
     */
    function __construct($options = array())
    {
        foreach ($options as $key => $val) {
            $this->$key = $val;
        }
    }

    /**
     * Adds new column to resulting select by querying $field.
     *
     * Examples:
     *  $q->field('name');
     *
     * Second argument specifies table for regular fields
     *  $q->field('name','user');
     *  $q->field('name','user')->field('line1','address');
     *
     * Array as a first argument will specify mulitple fields, same as calling field() multiple times
     *  $q->field(['name','surname']);
     *
     * Associative array will assume that "key" holds the alias. Value may be object.
     *  $q->field(['alias'=>'name','alias2'=>surname']);
     *  $q->field(['alias'=>$q->expr(..), 'alias2'=>$q->dsql()->.. ]);
     *
     * You may use array with aliases together with table specifier.
     *  $q->field(['alias'=>'name','alias2'=>surname'],'user');
     *
     * You can specify $q->expr() for calculated fields. Alias is mandatory.
     *  $q->field( $q->expr('2+2'),'alias');                // must always use alias
     *
     * You can use $q->dsql() for subqueries. Alias is mandatory.
     *  $q->field( $q->dsql()->table('x')... , 'alias');    // must always use alias
     *
     * @param string|array $field Specifies field to select
     * @param string       $table Specify if not using primary table
     * @param string       $alias Specify alias for this field
     *
     * @return DB_dsql $this
     */
    function field($field, $table = null, $alias = null)
    {
        // field is passed as string, may contain commas
        if (is_string($field) && strpos($field, ',') !== false) {
            $field = explode(',', $field);
        } elseif (is_object($field)) {
            $alias = $table;
            $table = null;
        }

        // recursively add array fields
        if (is_array($field)) {
            foreach ($field as $alias => $f) {
                if (is_numeric($alias)) {
                    $alias = null;
                }
                $this->field($f, $table, $alias);
            }
            return $this;
        }
        $this->args['fields'][] = [$field, $table, $alias];
        return $this;
    }

    /**
     * Returns template component for [field]
     *
     * @return string Parsed template chunk
     */
    function render_field()
    {
        // will be joined for output
        $result=[];

        // If no fields were defined, use default_field
        if (!isset($this->args['fields']) || !($this->args['fields'])) {
            if ($this->defaultField instanceof DB_dsql) {
                return $this->consume($this->defaultField);
            }
            return (string)$this->defaultField;
        }

        foreach ($this->args['fields'] as $row) {
            list($field,$table,$alias)=$row;

            // Do not use alias, if it's same as field
            if ($alias===$field) {
                $alias=null;
            }

            // Will parameterize the value and backtick if necessary.
            $field=$this->consume($field);

            // TODO: Commented until I figure out what this does
            /*
            if (!$field) {
                $field=$table;
                $table=null;
            }
            */

            if (!is_null($table)) {
                // table cannot be expression, so only backtick
                $field=$this->escape($table).'.'.$field;
            }

            if ($alias && $alias!==null) {
                // alias cannot be expression, so only backtick
                $field.=' '.$this->escape($alias);
            }
            $result[]=$field;
        }
        return join(',', $result);
    }

    /**
     * Recursively renders sub-query or expression, combining parameters.
     * If the argument is more likely to be a field, use tick=true
     *
     * @param object|string $dsql Expression
     * @param boolean       $tick Preferred quoted style
     *
     * @return string Quoted expression
     */
    function consume($dsql)
    {
        if ($dsql===null) {
            return null;
        }

        /** TODO: TEMPORARILY removed, ATK feature, implement with traits **/
        /*
        if (is_object($dsql) && $dsql instanceof Field) {
            $dsql=$dsql->getExpr();
        }
        */
        if (!is_object($dsql) || !$dsql instanceof Query) {
            return $this->escape($dsql);
        }
        $dsql->params = &$this->params;
        $ret = $dsql->_render();
        if ($dsql->mode==='select') {
            $ret='('.$ret.')';
        }
        unset($dsql->params);
        $dsql->params=[];
        return $ret;
    }

    /**
     * Escapes argument by adding backtics around it. This will allow you to use reserved
     * SQL words as table or field names such as "table"
     *
     * @param string $s any string
     *
     * @return string Quoted string
     */
    function escape($identifier)
    {
        // Supports array
        if (is_array($identifier)) {
            $out=[];
            foreach ($identifier as $ss) {
                $out[]=$this->escape($ss);
            }
            return $out;
        }

        if (!$this->escapeChar
            || is_object($identifier)
            || $identifier==='*'
            || strpos($identifier, '.')!==false
            || strpos($identifier, '(')!==false
            || strpos($identifier, $this->escapeChar)!==false
        ) {
            return $identifier;
        }

        return $this->escapeChar.$identifier.$this->escapeChar;
    }

    public function table($table)
    {
        return (boolean)$table;
    }


    public function render(){

    }
}
