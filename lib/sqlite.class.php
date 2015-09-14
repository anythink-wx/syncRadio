<?php
/**
 * Created by PhpStorm.
 * User: anythink
 * Date: 15/8/13
 * Time: 下午9:50
 */
class db{
    private $file = 'music.db';
    private $db;

    function __construct(){
        if(!$this->db  = new SQLite3(ROOT.$this->file)){
            $this->db->lastErrorMsg();
        }
    }

    function first($table,$where=''){
        if($where){
            $where = " where ".$where;
        }
        $sql = "select * from " .$table .' '. $where;
        if($res = $this->db->query($sql)){
			$row = $res->fetchArray(SQLITE3_ASSOC);
            return $row;
        }else{
            return false;
        }
    }

	function findAll($table,$where=''){
		if($where){
			$where = " where ".$where;
		}
		$sql = "select * from " .$table .' '. $where;
		echo $sql.PHP_EOL;
		if($res = $this->db->query($sql)){
			$data = [];
			while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
				if($row){
					$data[] = $row;
				}
			}
			return $data;
		}else{
			return false;
		}
	}


    function update($table,$conditions,$row){
        if(empty($row))return FALSE;

        if(is_array($conditions)){
            $join = [];
            foreach( $conditions as $key => $condition ){
                $join[] = "`{$key}` = '{$condition}'";
            }
            $where = "WHERE ".join(" AND ",$join);
        }else{
            if(null != $conditions)$where = "WHERE ".$conditions;
        }


		$vals = [];
        foreach($row as $key => $value){
            $value = $value;
            $vals[] = "`{$key}` = '{$value}'";
        }
        $values = join(", ",$vals);
        $sql = "UPDATE $table SET {$values} {$where}";
        echo $sql.PHP_EOL;
        return $this->db->exec($sql);
    }


    function create($table,$row){
		$cols = $vals = [];
        foreach($row as $key => $value){
            $cols[] = '`'.$key.'`';
            $vals[] = "'$value'";
        }
        $col = join(',', $cols);
        $val = join(',', $vals);
        $sql = "INSERT INTO $table ({$col}) VALUES ({$val})";
        echo $sql.PHP_EOL;
        return $this->db->exec($sql);

    }

    function truncate($table){
        $sql = "DELETE FROM $table ";
        echo $sql.PHP_EOL;
        return $this->db->exec($sql);
    }
}