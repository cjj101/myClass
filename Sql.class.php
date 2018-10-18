<?php

/**
 * 数据库类 可用于 mysql等结构化数据库
 * 通过initDb方法初始化 传入数据库配置 结构为
 * $dbConfig=[
    'db'=>[
        'dsn'=>'mysql:host=localhost;dbname=football',
            'username' => 'root',
            'password' => 'root',
            'charset' => 'utf8',
            'persistent' => true,
        ],
        'mydb'=>[
            'dsn'=>'mysql:host=localhost;dbname=games',
            'username' => 'root',
            'password' => 'root',
            'charset' => 'utf8',
            'persistent' => true,
        ],
    ];
 * 可以通过Sql::initDb->db 获取名为db的数据库 私有化属性$conn为此数据库的连接
 */
class Sql{
    private static $db;
    private $conn;
    private $prepare;

    /**
     * 连接数据库 param为数据库参数
     * 如果在命令行模式使用 自动长连接
     * @param $param
     */
    private function __construct($param){
        try{
            // 如果是命令行模式 进行持久化连接
            $extra = [];
            if(isset($param['charset'])){
                $extra[\PDO::MYSQL_ATTR_INIT_COMMAND]="set names ".$param['charset'];
            }
            if(php_sapi_name() == "cli"){
                $extra[\PDO::ATTR_PERSISTENT]=true;
            }
            if($extra){
                $this->conn=new \PDO($param['dsn'],$param['username'],$param['password'],$extra);
            }else{
                $this->conn=new \PDO($param['dsn'],$param['username'],$param['password']);
            }

        }catch(\PDOException $e){
            die ("数据库连接失败!:".$e->getMessage());
        }
    }

    /**
     * 获取数据库的单例
     * @param $dbConfig
     * @return StdClass
     */
    static public function initDb($dbConfig){
        if(!self::$db){
            self::$db = new StdClass();
            foreach($dbConfig as $k=>$v){
                self::$db->$k = new Sql($v);
            }
        }
        return self::$db;
    }

    /**
     * 返回数据库的连接
     * @return PDO
     */
    private function getConn(){
        return $this->conn;
    }

    /**
     * 关闭预处理语句
     */
    public function closePrepare(){
        $this->prepare->closeCursor();
        $this->prepare=null;
        return true;
    }

    /**
     * 不使用预处理方式执行搜索语句 并获取全部数据
     * @param $sql
     * @return array|null
     */
    public function getAllUnsafe($sql){
        $result = [];
        $return = $this->getConn()->query($sql,\PDO::FETCH_ASSOC);
        foreach ($return as $value){
            $result[]=$value;
        }
        if(!$result){
            $result = null;
        }
        return $result;
    }

    /**
     * 不使用预处理方式执行sql语句 并获取结果
     * @param $sql
     * @return array|null
     */
    public function query($sql){
        $return = $this->getConn()->query($sql,\PDO::FETCH_ASSOC);
        foreach($return as $value){
            return $value;
        }
    }

    /**
     * 根据简单查询传入参数 生成sql语句
     * @param $table
     * @param array $condition
     * @param string $field
     * @param string $order
     * @param string $limit
     * @return array
     */
    private function createSql($table,$condition=[],$field='',$order='',$limit=''){
        if(is_array($condition)&&$condition){
            $where = '';
            $bind = [];
            foreach($condition as $k=>$v){
                $where.=' `'.$k.'`=:'.$k.' and';
                $bind[':'.$k]=$v;
            }
            $where=trim($where,'and');
            $where=' where'.$where;
        }else{
            $where = '';
            $bind = [];
        }
        if(is_array($field)){
            $fieldStr = '';
            foreach($field as $v){
                $fieldStr.='`'.$v.'`,';
            }
        }elseif($field){
            $fieldStr=$condition;
        }else{
            $fieldStr='*';
        }
        $fieldStr=trim($fieldStr,',');
        $sql = 'select '.$fieldStr.' from '.$table.$where;
        if($order){
            $sql.='order by '.$order.' ';
        }
        if($limit){
            $sql.='limit '.$limit.' ';
        }
        return ['sql'=>$sql,'bind'=>$bind];
    }

    /**
     * 简单查询 用于不怎么复杂的语句 获取一条信息
     * @param $table
     * @param array $condition
     * @param string $field
     * @param string $order
     * @param string $limit
     * @return bool|mixed
     */
    public function simpleGetOne($table,$condition=[],$field='',$order='',$limit=''){
        if(!$table){
            return false;
        }
        $rs = $this->createSql($table,$condition,$field,$order,$limit);
        $result = $this->getOne($rs['sql'],$rs['bind']);
        return $result;
    }

    /**
     * 简单查询 用于不怎么复杂的语句 获取多条信息
     * @param $table
     * @param array $condition
     * @param string $field
     * @param string $order
     * @param string $limit
     * @return array|bool
     */
    public function simpleGetAll($table,$condition=[],$field='',$order='',$limit=''){
        if(!$table){
            return false;
        }
        $rs = $this->createSql($table,$condition,$field,$order,$limit);
        $result = $this->getAll($rs['sql'],$rs['bind']);
        return $result;
    }

    /**
     * 通过sql语句 预处理查询 获取多条信息
     * @param $sql
     * @param array $bind
     * @return array
     */
    public function getAll($sql,$bind=[]){
        $this->prepare = $this->getConn()->prepare($sql,array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
        $this->prepare = $this->getConn()->prepare($sql);
        if(is_array($bind)&&$bind){
            $this->prepare->execute($bind);
        }else{
            $this->prepare->execute();
        }
        $result=$this->prepare->fetchAll(\PDO::FETCH_ASSOC);
        $this->closePrepare();
        return $result;
    }

    /**
     * 通过sql语句 预处理查询 获取一条语句
     * @param $sql
     * @param array $bind
     * @return mixed
     */
    public function getOne($sql,$bind=[]){
        $this->prepare = $this->getConn()->prepare($sql,array(\PDO::ATTR_CURSOR => \PDO::CURSOR_FWDONLY));
        if(is_array($bind)&&$bind){
            $this->prepare->execute($bind);
        }else{
            $this->prepare->execute();
        }
        //$result=$this->prepare->fetch(\PDO::FETCH_ASSOC);
        $result=$this->prepare->fetch(\PDO::FETCH_ASSOC);
        $this->closePrepare();
        return $result;
    }

    /**
     * 通过sql语句 预处理查询 结果数量
     * @param $sql
     * @param array $bind
     * @return mixed
     */
    public function getCount($sql,$bind=[]){
        $return = $this->getOne($sql,$bind);
        foreach($return as $v){
            return (int)$v;
        }
    }

    /**
     * 插入一条数据
     * @param $table
     * @param array $newData
     * @return bool|int
     */
    public function insert($table,$newData=[]){
        if(is_array($newData)&&$newData){
            $field = '';
            $bind = '';
            foreach($newData as $k=>$v){
                $field.=$k.',';
                $bind.=':'.$k.',';
            }
            $field=rtrim($field,',');
            $bind=rtrim($bind,',');
            $sql = 'insert into '.$table.' ('.$field.') values ('.$bind.')';
            $this->prepare = $this->getConn()->prepare($sql);
            $this->prepare->execute($newData);
            $lastInsertId = (int)$this->getConn()->lastInsertId();
            $this->closePrepare();
            return $lastInsertId;
        }else{
            return false;
        }
    }

    /**
     * 插入多条数据
     * @param $table
     * @param array $batchNewData
     * @return bool
     */
    public function batchInsert($table,$batchNewData=[]){
        if(is_array($batchNewData)&&$batchNewData){
            $field = '';
            $bind = '';
            foreach($batchNewData[0] as $k=>$v){
                $field.=$k.',';
                $bind.=':'.$k.',';
            }
            $field=rtrim($field,',');
            $bind=rtrim($bind,',');
            $sql = 'insert into '.$table.' ('.$field.') values ('.$bind.')';
            $this->prepare = $this->getConn()->prepare($sql);
            foreach($batchNewData as $v){
                $this->prepare->execute($v);
            }
            return true;
        }else{
            return false;
        }
    }

    /**
     * 更新数据
     * @param $table
     * @param $condition
     * @param array $newData
     * @return bool
     */
    public function update($table,$condition,$newData=[]){
        if(is_array($newData)&&$newData){
            $updateData='';
            foreach($newData as $k=>$v){
                $updateData.='`'.$k.'`="'.$v.'",';
            }
            $updateData=trim($updateData,',');
            if(is_array($condition)){
                $where = '';
                foreach($condition as $k=>$v){
                    $where.=' where `'.$k.'`="'.$v.'",';
                }
            }elseif($condition){
                    $where=' where '.$condition;
            }else{
                $where = '';
            }
            $where=trim($where,',');
            $sql='update '.$table.' set '.$updateData.$where;
            $this->conn->query($sql);
            return true;
        }else{
            return false;
        }
    }
}


