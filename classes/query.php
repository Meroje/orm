<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package		Fuel
 * @version		1.0
 * @author		Fuel Development Team
 * @license		MIT License
 * @copyright	2010 - 2011 Fuel Development Team
 * @link		http://fuelphp.com
 */

namespace Orm;

class Query {

	public static function factory($model, $options = array())
	{
		return new static($model, $options);
	}

	/**
	 * @var  string  classname of the model
	 */
	protected $model;

	/**
	 * @var  string  table alias
	 */
	protected $alias = 't0';

	/**
	 * @var  array  relations to join on
	 */
	protected $relations = array();

	/**
	 * @var  array  tables to join without returning any info
	 */
	protected $joins = array();

	/**
	 * @var  array  fields to select
	 */
	protected $select = array();

	/**
	 * @var  int  max number of returned rows
	 */
	protected $limit;

	/**
	 * @var  int  offset
	 */
	protected $offset;

	/**
	 * @var  array  where conditions
	 */
	protected $where = array();

	/**
	 * @var  array  order by clauses
	 */
	protected $order_by = array();

	/**
	 * @var  array  group by clauses
	 */
	protected $group_by = array();

	/**
	 * @var  array  values for insert or update
	 */
	protected $values = array();

	protected function __construct($model, $options, $table_alias = null)
	{
		$this->model = $model;

		foreach ($options as $opt => $val)
		{
			switch ($opt)
			{
				case 'select':
					$val = (array) $val;
					call_user_func_array(array($this, 'select'), $val);
					break;
				case 'related':
					$val = (array) $val;
					$this->related($val);
					break;
				case 'where':
					$obj = $this;
					$where_func = null;
					$where = function (array $val, $or = false) use (&$where_func, $obj)
					{
						$or and $obj->or_where_open();
						foreach ($val as $k_w => $v_w)
						{
							if (is_array($v_w) and ! empty($v_w[0]) and is_string($v_w[0]))
							{
								call_user_func_array(array($obj, ($k_w == 'or' ? 'or_' : '').'where'), $v_w);
							}
							elseif (is_int($k_w) or $k_w == 'or')
							{
								$k_w == 'or' ? $obj->or_where_open() : $obj->where_open();
								$where($v_w, $k_w == 'or');
								$k_w == 'or' ? $obj->or_where_close() : $obj->where_close();
							}
							else
							{
								$obj->where($k_w, $v_w);
							}
						}
						$or and $obj->or_where_close();
					};
					$where_func = $where;
					$where($val);
					break;
				case 'order_by':
					$val = (array) $val;
					$this->order_by($val);
					break;
				case 'limit':
					$this->limit($val);
					break;
				case 'offset':
					$this->offset($val);
					break;
			}
		}
	}

	/**
	 * Select which properties are included, each as its own param. Or don't give input to retrieve
	 * the current selection.
	 *
	 * @return  void|array
	 */
	public function select()
	{
		$fields = func_get_args();

		if (empty($fields))
		{
			if (empty($this->select))
			{
				$fields = array_keys(call_user_func($this->model.'::properties'));

				if (empty($fields))
				{
					throw new Exception('No properties found in model.');
				}
				foreach ($fields as $field)
				{
					$this->select($field);
				}
			}

			$out = array();
			foreach($this->select as $k => $v)
			{
				$out[] = array($v, $k);
			}
			return $out;
		}

		$i = count($this->select);
		foreach ($fields as $val)
		{
			strpos($val, '.') === false ? 't0.'.$val : $val;
			$this->select[$this->alias.'_c'.$i++] = $this->alias.'.'.$val;
		}

		return $this;
	}

	/**
	 * Set the limit
	 *
	 * @param  int
	 */
	public function limit($limit)
	{
		$this->limit = intval($limit);

		return $this;
	}

	/**
	 * Set the offset
	 *
	 * @param  int
	 */
	public function offset($offset)
	{
		$this->offset = intval($offset);

		return $this;
	}

	/**
	 * Set where condition
	 *
	 * @param	string	property
	 * @param	string	comparison type (can be omitted)
	 * @param	string	comparison value
	 */
	public function where()
	{
		$condition = func_get_args();
		is_array(reset($condition)) and $condition = reset($condition);

		return $this->_where($condition);
	}

	/**
	 * Set or_where condition
	 *
	 * @param  string  property
	 * @param  string  comparison type (can be omitted)
	 * @param  string  comparison value
	 */
	public function or_where()
	{
		$condition = func_get_args();
		is_array(reset($condition)) and $condition = reset($condition);

		return $this->_where($condition, 'or_where');
	}

	/**
	 * Does the work for where() and or_where()
	 *
	 * @param  array
	 * @param  string
	 * @todo   adding the table alias needs to work better, this will cause problems with WHERE IN
	 */
	public function _where($condition, $type = 'and_where')
	{
		if (is_array(reset($condition)) or is_string(key($condition)))
		{
			foreach ($condition as $k_c => $v_c)
			{
				is_string($k_c) and $v_c = array($k_c, $v_c);
				$this->_where($v_c, $type);
			}
			return $this;
		}

		strpos($condition[0], '.') === false and $condition[0] = $this->alias.'.'.$condition[0];
		if (count($condition) == 2)
		{
			$this->where[] = array($type, array($condition[0], '=', $condition[1]));
		}
		elseif (count($condition) == 3)
		{
			$this->where[] = array($type, $condition);
		}
		else
		{
			throw new Exception('Invalid param count for where condition.');
		}

		return $this;
	}

	/**
	 * Open a nested and_where condition
	 */
	public function and_where_open()
	{
		$this->where[] = array('and_where_open', array());

		return $this;
	}

	/**
	 * Close a nested and_where condition
	 */
	public function and_where_close()
	{
		$this->where[] = array('and_where_close', array());

		return $this;
	}

	/**
	 * Alias to and_where_open()
	 */
	public function where_open()
	{
		$this->where[] = array('and_where_open', array());

		return $this;
	}

	/**
	 * Alias to and_where_close()
	 */
	public function where_close()
	{
		$this->where[] = array('and_where_close', array());

		return $this;
	}

	/**
	 * Open a nested or_where condition
	 */
	public function or_where_open()
	{
		$this->where[] = array('or_where_open', array());

		return $this;
	}

	/**
	 * Close a nested or_where condition
	 */
	public function or_where_close()
	{
		$this->where[] = array('or_where_close', array());

		return $this;
	}

	/**
	 * Set the order_by
	 *
	 * @param  string|array
	 * @param  string|null
	 */
	public function order_by($property, $direction = 'ASC')
	{
		if (is_array($property))
		{
			foreach ($property as $p => $d)
			{
				is_int($p) ? $this->order_by($d, $direction) : $this->order_by($p, $d);
			}
			return $this;
		}

		strpos($property, '.') === false and $property = $this->alias.'.'.$property;
		$this->order_by[$property] = $direction;

		return $this;
	}

	/**
	 * Set a relation to include
	 *
	 * @param  string
	 */
	public function related($relation, $conditions = array())
	{
		if (is_array($relation))
		{
			foreach ($relation as $k_r => $v_r)
			{
				is_array($v_r) ? $this->related($k_r, $v_r) : $this->related($v_r);
			}
			return $this;
		}

		if (strpos($relation, '.'))
		{
			$rels = explode('.', $relation);
			$model = $this->model;
			foreach ($rels as $r)
			{
				$rel = call_user_func(array($model, 'relations'), $r);
				if (empty($rel))
				{
					throw new UndefinedRelation('Relation "'.$r.'" was not found in the model "'.$model.'".');
				}
				$model = $rel->model_to;
			}
		}
		else
		{
			$rel = call_user_func(array($this->model, 'relations'), $relation);
			if (empty($rel))
			{
				throw new UndefinedRelation('Relation "'.$relation.'" was not found in the model.');
			}
		}

		$this->relations[$relation] = array($rel, $conditions);

		if ( ! empty($conditions['related']))
		{
			$conditions['related'] = (array) $conditions['related'];
			foreach ($conditions['related'] as $k_r => $v_r)
			{
				is_array($v_r) ? $this->related($relation.'.'.$k_r, $v_r) : $this->related($relation.'.'.$v_r);
			}

			unset($conditions['related']);
		}

		return $this;
	}

	/**
	 * Add a table to join, consider this a protect method only for Orm package usage
	 *
	 * @param  array
	 */
	public function _join(array $join)
	{
		$this->joins[] = $join;

		return $this;
	}

	/**
	 * Set any properties for insert or update
	 *
	 * @param  string|array
	 * @param  mixed
	 */
	public function set($property, $value = null)
	{
		if (is_array($property))
		{
			foreach ($property as $p => $v)
			{
				$this->set($p, $v);
			}
			return $this;
		}

		$this->values[$property] = $value;

		return $this;
	}

	/**
	 * Build a select, delete or update query
	 *
	 * @param   Database_Query
	 * @param   string|select  either array for select query or string update, delete, insert
	 * @return  array          with keys query and relations
	 */
	public function build_query($query, $columns = array(), $type = 'select')
	{
		// Get the limit
		if ( ! is_null($this->limit))
		{
			$query->limit($this->limit);
		}

		// Get the offset
		if ( ! is_null($this->offset))
		{
			$query->offset($this->offset);
		}

		// Get the order
		if ( ! empty($this->order_by))
		{
			foreach ($this->order_by as $property => $direction)
			{
				if (strpos($property, $this->alias.'.') === 0)
				{
					$query->order_by($type == 'select' ? $property : substr($property, strlen($this->alias.'.')), $direction);
					unset($this->order_by[$property]);
				}
			}
		}

		// Get the group
		if ( ! empty($this->group_by))
		{
			$query->group_by($this->group_by);
		}

		if ( ! empty($this->where))
		{
			$open_nests = 0;
			foreach ($this->where as $key => $where)
			{
				list($method, $conditional) = $where;

				if (empty($conditional) or $open_nests > 0)
				{
					strpos($method, '_open') and $open_nests++;
					strpos($method, '_close') and $open_nests--;
					continue;
				}

				if (strpos($conditional[0], $this->alias.'.') === 0)
				{
					$type != 'select' and $conditional[0] = substr($conditional[0], strlen($this->alias.'.'));
					call_user_func_array(array($query, $method), $conditional);
					unset($this->where[$key]);
				}
			}
		}

		// If it's not a select we're done
		if ($type != 'select')
		{
			return array('query' => $query, 'models' => array());
		}

		$i = 1;
		$models = array();
		foreach ($this->relations as $name => $rel)
		{
			// when there's a dot it must be a nested relation
			if ($pos = strrpos($name, '.'))
			{
				if (empty($models[substr($name, 0, $pos)]['table'][1]))
				{
					throw new UndefinedRelation('Trying to get the relation of an unloaded relation, make sure you load the parent relation before any of its children.');
				}

				$alias = $models[substr($name, 0, $pos)]['table'][1];
			}
			else
			{
				$alias = $this->alias;
			}

			$models = array_merge($models, $rel[0]->join($alias, $name, $i++, $rel[1]));
		}

		if ($this->use_subquery())
		{
			// Get the columns for final select
			foreach ($models as $m)
			{
				foreach ($m['columns'] as $c)
				{
					$columns[] = $c;
				}
			}

			// make current query subquery of ultimate query
			$new_query = call_user_func_array('DB::select', $columns);
			$query = $new_query->from(array($query, $this->alias));
		}
		else
		{
			// add additional selected columns
			foreach ($models as $m)
			{
				foreach ($m['columns'] as $c)
				{
					$query->select($c);
				}
			}
		}

		// join tables
		foreach ($this->joins as $j)
		{
			$join_query = $query->join($j['table'], $j['join_type']);
			foreach ($j['join_on'] as $on)
			{
				$join_query->on($on[0], $on[1], $on[2]);
			}
		}
		foreach ($models as $m)
		{
			$join_query = $query->join($m['table'], $m['join_type']);
			foreach ($m['join_on'] as $on)
			{
				$join_query->on($on[0], $on[1], $on[2]);
			}
		}

		// Add any additional order_by and where clauses from the relations
		foreach ($models as $m)
		{
			if ( ! empty($m['order_by']))
			{
				foreach ((array) $m['order_by'] as $k_ob => $v_ob)
				{
					is_int($k_ob) ? $this->order_by($m['table'][1].'.'.$v_ob) : $this->order_by($m['table'][1].'.'.$k_ob, $v_ob);
				}
			}
			if ( ! empty($m['where']))
			{
				foreach ((array) $m['where'] as $k_w => $v_w)
				{
					if (is_int($k_w))
					{
						$v_w[0] = $m['table'][1].'.'.$v_w[0];
						call_user_func_array(array($this, 'where'), $v_w);
					}
					else
					{
						$this->where($m['table'][1].'.'.$k_w, $v_w);
					}
				}
			}
		}
		// Get the order
		if ( ! empty($this->order_by))
		{
			foreach ($this->order_by as $column => $direction)
			{
				// try to rewrite conditions on the relations to their table alias
				$dotpos = strrpos($column, '.');
				$relation = substr($column, 0, $dotpos);
				if ($dotpos > 0 and array_key_exists($relation, $models))
				{
					$column = $models[$relation]['table'][1].substr($column, $dotpos);
				}

				$query->order_by($column, $direction);
			}
		}

		// put omitted where conditions back
		if ( ! empty($this->where))
		{
			foreach ($this->where as $where)
			{
				list($method, $conditional) = $where;

				// try to rewrite conditions on the relations to their table alias
				if ( ! empty($conditional))
				{
					$dotpos = strrpos($conditional[0], '.');
					$relation = substr($conditional[0], 0, $dotpos);
					if ($dotpos > 0 and array_key_exists($relation, $models))
					{
						$conditional[0] = $models[$relation]['table'][1].substr($conditional[0], $dotpos);
					}
				}

				call_user_func_array(array($query, $method), $conditional);
			}
		}

		return array('query' => $query, 'models' => $models);
	}

	/**
	 * Determines whether a subquery is needed, is the case if there was a limit/offset on a join
	 *
	 * @return  bool
	 */
	public function use_subquery()
	{
		return ( ! empty($this->relations) and ( ! empty($this->limit) or ! empty($this->offset)));
	}

	/**
	 * Hydrate model instances with retrieved data
	 *
	 * @param  array   row from the database
	 * @param  array   relations to be expected
	 * @param  array   current result array (by reference)
	 * @param  string  model classname to hydrate
	 * @param  array   columns to use
	 */
	public function hydrate($row, $models, &$result, $model = null, $select = null)
	{
		$model = is_null($model) ? $this->model : $model;
		$select = is_null($select) ? $this->select() : $select;
		$obj = array();
		foreach ($select as $s)
		{
			$obj[preg_replace('/^t[0-9]+(_[a-z]+)?\\./uiD', '', $s[0])] = $row[$s[1]];
		}

		foreach ($model::primary_key() as $pk)
		{
			if (is_null($obj[$pk]))
			{
				return false;
			}
		}

		$cached_obj = $model::cached_object($obj);
		$pk         = $model::implode_pk($obj);
		if (is_array($result) and ! array_key_exists($pk, $result))
		{
			if ($cached_obj)
			{
				$cached_obj->_update_original($obj);
				$obj = $cached_obj;
			}
			else
			{
				$obj = $model::factory($obj, false);
			}
			$result[$pk] = $obj;
		}
		elseif ( ! is_array($result) and empty($result))
		{
			if ($cached_obj)
			{
				$cached_obj->_update_original($obj);
				$obj = $cached_obj;
			}
			else
			{
				$obj = $model::factory($obj, false);
			}
			$result = $obj;
		}
		else
		{
			$obj = is_array($result) ? $result[$pk] : $result;
		}

		$rel_objs = $obj->_relate();
		foreach ($models as $m)
		{
			if (empty($m['model']))
			{
				continue;
			}

			if ( ! array_key_exists($m['rel_name'], $rel_objs))
			{
				$rel_objs[$m['rel_name']] = $m['relation']->singular ? null : array();
			}

			if ((is_array($result) and ! in_array($model::implode_pk($obj), $result))
				or ! is_array($result) and empty($result))
			{
				$this->hydrate($row, ! empty($m['models']) ? $m['models'] : array(), $rel_objs[$m['rel_name']], $m['model'], $m['columns']);
			}
		}
		$obj->_relate($rel_objs);
		$obj->_update_original();

		return $obj;
	}

	/**
	 * Build the query and return hydrated results
	 *
	 * @return  array
	 */
	public function get()
	{
		// Get the columns
		$columns = $this->select();

		// Start building the query
		$select = $columns;
		if ($this->use_subquery())
		{
			$select = array();
			foreach ($columns as $c)
			{
				$select[] = $c[0];
			}
		}
		$query = call_user_func_array('DB::select', $select);

		// Set from table
		$query->from(array(call_user_func($this->model.'::table'), $this->alias));

		// Build the query further
		$tmp     = $this->build_query($query, $columns);
		$query   = $tmp['query'];
		$models  = $tmp['models'];

		// Make models hierarchical
		foreach ($models as $name => $values)
		{
			if (strpos($name, '.'))
			{
				unset($models[$name]);
				$rels = explode('.', $name);
				$ref =& $models[array_shift($rels)];
				foreach ($rels as $rel)
				{
					if (empty($ref['models']))
					{
						$ref['models'] = array($rel => array());
					}
					$ref =& $ref['models'][$rel];
				}
				$ref = $values;
			}
		}

		$rows = $query->execute()->as_array();
		$result = array();
		foreach ($rows as $row)
		{
			$this->hydrate($row, $models, $result);
		}

		// It's all built, now lets execute and start hydration
		return $result;
	}

	/**
	 * Get the Query as it's been build up to this point and return it as an object
	 *
	 * @return Database_Query
	 */
	public function get_query()
	{
		// Get the columns
		$columns = $this->select();

		// Start building the query
		$select = $columns;
		if ($this->use_subquery())
		{
			$select = array();
			foreach ($columns as $c)
			{
				$select[] = $c[0];
			}
		}
		$query = call_user_func_array('DB::select', $select);

		// Set from table
		$query->from(array(call_user_func($this->model.'::table'), $this->alias));

		// Build the query further
		$tmp     = $this->build_query($query, $columns);

		return $tmp['query'];
	}

	/**
	 * Build the query and return single object hydrated
	 *
	 * @return  Model
	 */
	public function get_one()
	{
		// get current limit and save it while fetching the first result
		$limit = $this->limit;
		$this->limit = 1;

		// get the result using normal find
		$result = $this->get();

		// put back the old limit
		$this->limit = $limit;

		return $result ? reset($result) : null;
	}

	/**
	 * Count the result of a query
	 *
	 * @param   bool  false for random selected column or specific column, only works for main model currently
	 * @return  int   number of rows OR false
	 */
	public function count($distinct = false)
	{
		$this->select or $this->select = call_user_func($this->model.'::primary_key');
		$select = reset($this->select);

		// Get the columns
		$columns = \DB::expr('COUNT('.($distinct ? 'DISTINCT ' : '').\Database_Connection::instance()->table_prefix().$this->alias.'.'.($distinct ?: $select).') AS count_result');

		// Remove the current select and
		$query = call_user_func('DB::select', $columns);

		// Set from table
		$query->from(array(call_user_func($this->model.'::table'), $this->alias));

		$tmp   = $this->build_query($query, $columns);
		$query = $tmp['query'];
		$count = $query->execute()->get('count_result');

		// Database_Result::get('count_result') returns a string | null
		if ($count === null)
		{
			return false;
		}

		return (int) $count;
	}

	/**
	 * Get the maximum of a column for the current query
	 *
	 * @param   string  column
	 * @return  mixed   maximum value OR false
	 */
	public function max($column)
	{
		is_array($column) and $column = array_shift($column);

		// Get the columns
		$columns = \DB::expr('MAX('.\Database_Connection::instance()->table_prefix().$this->alias.'.'.$column.') AS max_result');

		// Remove the current select and
		$query = call_user_func('DB::select', $columns);

		// Set from table
		$query->from(array(call_user_func($this->model.'::table'), $this->alias));

		$tmp   = $this->build_query($query, $columns);
		$query = $tmp['query'];
		$max   = $query->execute()->get('max_result');

		// Database_Result::get('max_result') returns a string | null
		if ($max === null)
		{
			return false;
		}

		return $max;
	}

	/**
	 * Get the minimum of a column for the current query
	 *
	 * @param   string  column
	 * @return  mixed   minimum value OR false
	 */
	public function min($column)
	{
		is_array($column) and $column = array_shift($column);

		// Get the columns
		$columns = \DB::expr('MIN('.\Database_Connection::instance()->table_prefix().$this->alias.'.'.$column.') AS min_result');

		// Remove the current select and
		$query = call_user_func('DB::select', $columns);

		// Set from table
		$query->from(array(call_user_func($this->model.'::table'), $this->alias));

		$tmp   = $this->build_query($query, $columns);
		$query = $tmp['query'];
		$min   = $query->execute()->get('min_result');

		// Database_Result::get('min_result') returns a string | null
		if ($min === null)
		{
			return false;
		}

		return $min;
	}

	/**
	 * Run INSERT with the current values
	 *
	 * @return  mixed  last inserted ID or false on failure
	 * @todo    work with relations
	 */
	public function insert()
	{
		$res = \DB::insert(call_user_func($this->model.'::table'), array_keys($this->values))
			->values(array_values($this->values))
			->execute();

		// Failed to save the new record
		if ($res[0] === 0)
		{
			return false;
		}

		return $res[0];
	}

	/**
	 * Run UPDATE with the current values
	 *
	 * @return  bool  success of update operation
	 * @todo    work with relations
	 */
	public function update()
	{
		// temporary disable relations and group_by
		$tmp_relations   = $this->relations;
		$this->relations = array();
		$tmp_group_by    = $this->group_by;
		$this->group_by  = array();

		// Build query and execute update
		$query = \DB::update(call_user_func($this->model.'::table'));
		$tmp   = $this->build_query($query, array(), 'update');
		$query = $tmp['query'];
		$res = $query->set($this->values)->execute();

		// put back any relations/group_by settings
		$this->relations = $tmp_relations;
		$this->group_by  = $tmp_group_by;

		return $res > 0;
	}

	/**
	 * Run DELETE with the current values
	 *
	 * @return  bool  success of delete operation
	 * @todo    cascade option and for relations and make sure they're removed
	 */
	public function delete()
	{
		// temporary disable relations and group_by
		$tmp_relations   = $this->relations;
		$this->relations = array();
		$tmp_group_by    = $this->group_by;
		$this->group_by  = array();

		// Build query and execute update
		$query = \DB::delete(call_user_func($this->model.'::table'));
		$tmp   = $this->build_query($query, array(), 'delete');
		$query = $tmp['query'];
		$res = $query->execute();

		// put back any relations/group_by settings
		$this->relations = $tmp_relations;
		$this->group_by  = $tmp_group_by;

		return $res > 0;
	}
}

/* End of file query.php */