<?php
class MysqlHelper{
	private $conn;
	public static $instance=null;
	private static $db=null;
	private $query;
	private $table;
	private $where;
	private $field;
	private $join;
	private $lastQuery;
	public static function getInstance(){
		if(is_null(self::$instance)){
			self::$instance=new MysqlHelper();
		}
		self::$instance->clearParam();
		return self::$instance;
	}
	private function __construct(){
		if(is_null(self::$db)){
			self::$db=new mysqli(MBM_DB_HOST,MBM_DB_USER,MBM_DB_PASSWORD,MBM_DB_NAME); 
		}
		if(mysqli_connect_error()){ 
			die('Connect Error ('.mysqli_connect_errno().') '.mysqli_connect_error()); 
        } 
        self::$db->set_charset('utf8'); 
	}
	//输入sql语句
	public function query($sql){
		$this->query=$sql;
		return self::$instance;
	}
	//输入查询字段
	public function select($select){
		$this->field=$select;
		return self::$instance;
	}
	//输入查询表单
	public function from($table){
		$this->table=$table;
		return self::$instance;
	}
	//条件搜索
	public function where($where){
		if(isset($where)){
			if(is_array($where)){
				$sql=' where';
				foreach($where as $k=>$v){
					$sql.=' `'.$k.'`="'.$v.'" and';
				}
				$this->where=trim($sql,'and');
			}else{
				$this->where=' where '.$where;
			}
		}
		return self::$instance;
	}
	//关联查询
	public function join($join){
		$this->join=$join;
		return self::$instance;
	}
	//获取多条数据
	public function getAll(){
		if($this->query){
			$query=$this->query;
		}else{
			if(!$this->table) return false;
			$field=$this->field?$this->field:'*';
			$query='select '.$field.' from '.$this->table.' '.$this->join.$this->where;
		}
		$this->query=$query;
		$result=$this->execute();
		$values=[];
		while($value=$result->fetch_assoc()){
			$values[]=$value;
		}
		return $values;
	}
	//获取一条数据
	public function getOne(){
		if($this->query) return $this->execute()->fetch_assoc();
		if(!$this->table) return false;
		$field=$this->field?$this->field:'*';
		$query='select '.$field.' from '.$this->table.' '.$this->join.$this->where;
		$this->query=$query;
		$result=$this->execute();
		return $result->fetch_assoc();
	}
	//更新数据
	public function update($data){
		if(!$this->table) return false;
		$updateData='';
		foreach($data as $k=>$v){
			$updateData.='`'.$k.'`="'.$v.'",';
		}
		$updateData=trim($updateData,',');
		$sql='update '.$this->table.' set '.$updateData.$this->where;
		$this->query=$sql;
		$result=$this->execute();
		return $result;
	}
	//删除数据
	public function delete($id=null){
		if(!$this->table) return false;
		if(is_numeric($id)&&$id){
			$sql='delete from '.$this->table.' where id='.$id;
		}else{
			$sql='delete from '.$this->table.$this->where;
		}
		$this->query=$sql;
		$result=$this->execute();
		return $result;
	}
	//初始化搜索条件
	private function clearParam(){
		$this->query=null;
		$this->table=null;
		$this->where=null;
		$this->field=null;
		$this->join=null;
		//var_dump($this);
	}
	//插入单条数据
	public function insert($table,$param){
		$sql='INSERT INTO '.$table.' ';
		$keys='';
		$values='';
		foreach($param as $k=>$v){
			$keys.=',`'.$k.'`';
			$values.=',"'.$v.'"';
		}
		$keys='('.trim($keys,',').')';
		$values='('.trim($values,',').')';
		$sql=$sql.$keys.' values '.$values;
		$this->query=$sql;
		//echo $sql;
		if($this->execute()){
			return self::$db->insert_id;
		}else{
			return false;
		}
	}
	//执行sql语句
	public function execute(){
		if($this->query){
			$query=$this->query;
			$this->lastQuery=$query;
			$result=self::$db->query($query);
			return $result;
		}else{
			self::$db->dump_debug_info();
			return false;
		}
	}
	//获取最后一条sql指令
	public function getLastSql(){
		return $this->lastQuery;
	}
}
//M方法 获取表
function M($table=null){
	if($table){
		return MysqlHelper::getInstance()->from($table);
	}else{
		return MysqlHelper::getInstance();
	}
}
?>