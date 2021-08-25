<?php

class db_Logger {

	protected $queries = array();

	static private $instance = null;

	public function getQueries()
	{
		return $this->queries;
	}
	
	public function startQuery($sql, $params)
	{
		$this->start = microtime(true);
		$this->queries[] = array('sql' => $sql, 'params' => $params, 'time' => 0);
	}

	public function stopQuery($success, $errmsg = null, $errcode = null)
	{
		$this->queries[(count($this->queries)-1)]['time'] = (microtime(true) - $this->start)*1000;
		$this->queries[(count($this->queries)-1)]['error'] = $errmsg;
		$this->queries[(count($this->queries)-1)]['errno'] = $errcode;
		$this->queries[(count($this->queries)-1)]['affected'] = -1;
	}

	public function getDump() {
		ob_start();
		$this->dump();
		$out = ob_get_contents();
		ob_end_clean();
		return $out;		
	}
	
	public function dump()
	{
		if (php_sapi_name() == 'cli')
		{
			foreach ($this->queries as $k => $i)
			{
				print (($k + 1) . ". {$i['sql']} {$i['error']} [".implode(',',$i['params'])."]\n");
			}
		}
		else
		{
			$_queriesCnt = count($this->queries);
			$_queriesTime = round(array_reduce($this->queries, create_function('$x,$y', 'return ($y[\'time\']+$x);')),3);

			?>
			<table class="table table-striped table-bordered table-hover" summary="SQL Log">
				<caption><?=$_queriesCnt?> query(ies) took <?=$_queriesTime?> ms</caption>
				<thead>
					<tr><th>Nr</th><th>Query</th><th>Params</th><th>Error</th><th>Affected</th><th>Num. rows</th><th>Took (ms)</th></tr>
				</thead>
				<tbody>
			<?php foreach ($this->queries as $k => $i) { ?>
				<tr>
					<td ><?=$k + 1?></td>
					<td>
						<div><?=nl2br($i['sql'])?></div>
					</td>
					<td><?php var_export($i['params'])?></td>
					<td style="color:red"><?=$i['error']?></td>
					<td style="text-align: right"><?=$i['affected']?></td>
					<td style="text-align: right"><?=@$i['numRows']?></td>
					<td style="text-align: right"><?=round($i['time'],3)?></td>
				</tr>
			<?php } ?>
			</tbody>
			</table>
			<?php
		}
	}

	/**
	 *
	 * @return db_Logger 
	 */
	static public function getInstance()
	{
		if (self::$instance)
			return self::$instance;

		self::$instance = new self;
		return self::$instance;
	}
}