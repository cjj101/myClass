<?php
/**
 * add by zhangqingqing@gametrees.com 2017 04.05  QQ:4934347
 * MongoDB类
 *
 */


/**
 *  insert, update, delete operations.
 * For example: 用法介绍
 *
 * ```php
 * use \Server\Driver\MongoDB;
 * MongoDB::getInstance()->collection('users')->insert(['name' => 'John Smith', 'status' => 1]); 插入一条数据
 *
 * MongoDB::getInstance()->collection('users')->batchInsert([
 *  ['name' => 'John Smith', 'status' => 1],
 *  ['name' => 'Tom Sen', 'status' => 2],
 *  ...
 * ]);      // 插入多条数据
 *
 *
 * // 查询多条数据
 * $res=MongoDB::getInstance()->collection('mytest')->find(['age'=>['$gt'=>'25']],['age'],['limit'=>2,'skip'=>0,'sort'=>['age'=>-1]]);
 * $res=MongoDB::getInstance()->collection('mytest')->select(['age'])->limit(2)->offset(0)->order_by(['age'=>-1])->find();
 * 以上2种查询返回结果一样
 *
 * // 查询一条数据
 * $res=MongoDB::getInstance()->collection('mytest')->select(['age'])->order_by(['age'=>-1])->findOne(['age'=>['$gt'=>'25']]);
 *$res=MongoDB::getInstance()->collection('mytest')->findOne(['age'=>['$gt'=>'25']],['age'],['sort'=>['age'=>-1]]);
 * 以上2中查询返回结果一致
 *
 * // 根据条件更新数据
 * $condition=[
 *   'name'=>'Leslie6',
 *   ];
 *   $newData=[
 *   'age'=>26,
 *   ];
 *   $res=MongoDB::getInstance()->collection('mytest')->update($condition,$newData);
 *
 *
 * // 删除数据
 *  $condition=[
 *      'name'=>'Leslie6',
 *  ];
 *  $res=MongoDB::getInstance()->collection('mytest')->remove($condition);
 *
 *
 *   // 创建一个索引
 *   $index=[
 *       'name',
 *       'age'=>-1,
 *   ];
 *   $res=MongoDB::getInstance()->collection('mytest')->createIndex($index);
 *
 *  // 创建多个索引
 *  参见 createIndexes()方法
 *
 *  // 查询索引
 * $res=MongoDB::getInstance()->collection('mytest')->listIndexes();
 *
 * // 根据索引名称删除索引
 *   $res=MongoDB::getInstance()->collection('mytest')->dropIndexes('name_1_age_-1');
 *
 * ```
 */
class MyMongoDB
{
    protected $manager;
    protected $database;
    protected $collectionName;

    protected static $uniqueInstance = null;

    protected $conf=[];
    protected $document=[];
    protected $_writeConcern;
    protected $typeMap=[];

    public $orderBy=[];
    public $select=[];
    public $limit = 99999;
    public $offset = 0;

    /**     默认读取配置文件
     * @param string $host
     * @param string $port
     * @param string $database
     * @throws \Exception
     */
    public function __construct($host='',$port='',$database='')
    {
        if(empty($host)){
            $host     = '172.16.0.9';
            $port     = 27017;
            $database = 'football_server1';
        }

        try{
            $_uri = "mongodb://".$host.":".$port;
            $this->manager= new \MongoDB\Driver\Manager($_uri,['connectTimeoutMS' => 50000, 'socketTimeoutMS' => 50000]);
            $this->select_db($database);

            $this->typeMap = [
                'root' => 'array',
                'document' => 'array'
            ];
        }catch (\MongoConnectionException $e){
            $this->show_error($e->getMessage());

        }
    }


    /**
     * @param string $host
     * @param string $port
     * @param string $database
     * @return null|MongoDB
     */
    public static function getInstance($host='',$port='',$database='')
    {
        if (self::$uniqueInstance === null) {
            self::$uniqueInstance = new self($host,$port,$database);
        }
        return self::$uniqueInstance;
    }

    /**     设置操作数据库
     * @param string $database
     * @return $this
     */
    public function select_db($database=''){
        if(empty($database)){
            $database = $this->getDefaultDb();
        }
        $this->database=$database;
        return $this;
    }

    /**
     * 获取默认数据库 即配置文件里设置的db
     * return string  返回默认数据库名
     */
    public function getDefaultDb(){
        $dbName = $this->conf['name'];
        return $dbName;
    }

    /**     选中操作文档
     * @param null $collectionName
     * @return $this
     */
    public function collection($collectionName=null){
        if(!empty($collectionName)){
            $this->collectionName = $collectionName;
        }
        return $this;
    }


    /***--------- CURD -------**/


    /**
     * Inserts new document into collection. 插入一条数据
     * @param array $document document content 要插入数据内容
     * @param array $options list of options in format: optionName => optionValue.
     * @return ObjectID|bool inserted record ID, `false` - on failure.
     */
    public function insert($document, $options = [])
    {
        $this->document = [];
        $this->addInsert($document);
        $result = $this->executeBatch($options);

        if ($result['result']->getInsertedCount() < 1) {
            return false;
        }

        return reset($result['insertedIds']);
    }

    public function addInsert($document){
        $this->document[] = [
            'type' => 'insert',
            'document' => $document,
        ];
        return $this;

    }


    /**
     * Inserts several new rows into collection. 插入多条数据
     * @param array $documents 插入数据 arrays.
     * @param array $options list of options in format: optionName => optionValue.
     * @return array inserted data, each row will have "_id" key assigned to it.
     * @throws Exception on failure.
     */
    public function batchInsert($documents, $options = [])
    {
        if(!is_array($documents)){
            $this->show_error('Documents must be array');
            return false;

        }
        $this->document = [];
        foreach ($documents as $key => $document) {
            $this->document[$key] = [
                'type' => 'insert',
                'document' => $document
            ];
        }

        $result = $this->executeBatch($options);

        if ($result['result']->getInsertedCount() < 1) {
            return false;
        }

        return $result['insertedIds'];
    }


    /*
     * @param string $collectionName 表名称
     * @param array $options 附加参数 key=>value
     * */
    public function executeBatch($options = [])
    {
        $databaseName = $this->database === null ? $this->getDefaultDb() : $this->database;

        try {

            $batch = new \MongoDB\Driver\BulkWrite($options);

            $insertedIds = [];
            foreach ($this->document as $key => $operation) {
                switch ($operation['type']) {
                    case 'insert':
                        $insertedIds[$key] = $batch->insert($operation['document']);
                        break;
                    case 'update':
                        $batch->update($operation['condition'], $operation['document'], $operation['options']);
                        break;
                    case 'delete':
                        $batch->delete($operation['condition'], isset($operation['options']) ? $operation['options'] : []);
                        break;
                    default:
                        throw new \Exception("Unsupported batch operation type '{$operation['type']}'");
                }
            }
            $writeResult = $this->manager->executeBulkWrite($databaseName . '.' . $this->collectionName, $batch, $this->getWriteConcern());
            return [
                'insertedIds' => $insertedIds,
                'result' => $writeResult,
            ];

        } catch (\MongoException $e) {
            $this->show_error($e->getMessage() ." => " . json_encode($this->document));
            $this->show_error($e->getCode());
        }


    }


    /**
     * Executes this command.
     * @return \MongoDB\Driver\Cursor result cursor.
     * @throws Exception on failure.
     */
    public function execute()
    {
        $databaseName = $databaseName = $this->database === null ? $this->getDefaultDb() : $this->database;

        try {

            $mongoCommand = new \MongoDB\Driver\Command($this->document);
            $cursor = $this->manager->executeCommand($databaseName, $mongoCommand);
            $cursor->setTypeMap($this->typeMap);

        } catch (\MongoException $e) {
            echo "execute => ".$e->getMessage().PHP_EOL;
            echo "execute => ".$e->getCode().PHP_EOL;
            $this->show_error($e->getMessage());
        }

        return $cursor;
    }

    public function clear() {
        $this->orderBy=[];
        $this->select=[];
        $this->limit = 99999;
        $this->offset = 0;
    }

    /*
     * @param $error_message
     * return null
     * */
    public function show_error($error_message) {
        echo " Mongo Exception : ".$error_message . PHP_EOL;
    }

    /**
     * Drops this collection.
     * @throws Exception on failure.
     * @return bool whether the operation successful.
     */
    public function drop()
    {
        if($this->collectionName !=null){
            $this->document=['drop'=>$this->collectionName];

            $result = current($this->execute()->toArray());
            return $result['ok'] > 0;

        }

        return false;


    }

    /**
     * Executes this command as a mongo query
     * @param array $options query options.
     * $options=[
     *  'projection' => [
     *      'title' => 1,
     *      'article' => 1,
     *   ],
     *  'sort' => [
            'views' => -1
        ],
     * ]
     * @return \MongoDB\Driver\Cursor result cursor.
     * @throws Exception on failure
     */
    public function query($options = [])
    {
        $databaseName = $this->database === null ? $this->getDefaultDb() : $this->database;

        $_options=[];
        if(!empty($this->select)){
            $_options['projection'] = $this->select;
        }

        if(!empty($this->limit)){
            $_options['limit'] = $this->limit;
        }

        if(!empty($this->offset)){
            $_options['skip'] = $this->offset;
        }
        if(!empty($this->orderBy)){
            $_options['sort'] = $this->orderBy;
        }
        $options=array_merge($_options,$options);

        if (array_key_exists('limit', $options)) {
            if ($options['limit'] === null) {
                unset($options['limit']);
            } else {
                $options['limit'] = (int)$options['limit'];
            }
        }

       /* if($options['limit'] > 1) {
            echo " MongoDB .query | limit :" . $options['limit'] . PHP_EOL;
            echo "  MongoDB .query |  databaseName : ".$databaseName . '.' . $this->collectionName." , options : ". json_encode($options) . PHP_EOL;
        }*/

        if (array_key_exists('skip', $options)) {
            if ($options['skip'] === null) {
                unset($options['skip']);
            } else {
                $options['skip'] = (int)$options['skip'];
            }
        }

        if(array_key_exists('projection',$options)){
            $options['projection']= $this->buildSelectFields($options['projection']);

        }

        try {

            $query = new \MongoDB\Driver\Query($this->document, $options);
            $cursor = $this->manager->executeQuery($databaseName . '.' . $this->collectionName, $query);
            $cursor->setTypeMap($this->typeMap);
            $this->clear();
        } catch (\MongoException $e) {
            $this->show_error($e->getMessage());
        }

        return $cursor;
    }

    /**
     * Returns a cursor for the search results.
     * @param array $condition 查询条件
     * @param array $fields 字段
     * @param array $options
     * @return array
     */
    public function find($condition=[],$fields = [], $options = [])
    {
        if (!empty($fields)) {
            $options['projection'] = $fields;
        }
        $this->buildCondition($condition);
        $cursor=$this->query($options);
        return $cursor->toArray();
       // return $this->fetchRows($cursor);
    }

    /**
     * Returns a single document.
     * @param array $condition 查询条件
     * @param array $fields fields to be selected
     * @param array $options query options (available since 2.1).
     * @return array|null the single document. Null is returned if the query results in nothing.
     */
    public function findOne($condition=[], $fields = [],$options = [])
    {
        $options['limit'] = 1;
        if (!empty($fields)) {
            $options['projection'] = $fields;
        }
        $this->buildCondition($condition);
        $cursor=$this->query($options);
        return $this->fetchRows($cursor,false);

    }

    /**
     * Updates a document and returns it. 查找并更新数据
     * @param array $condition query condition 查询条件
     * @param array $update update criteria 需要更新的数据
     * @param array $options list of options in format: optionName => optionValue. 附加参数
     * $options['fields']=>['name',,],
     * $options['sort']=>['name'=>-1],
     * $options['upsert']=>true,
     * @return array|null the original document, or the modified document when $options['new'] is set.
     */
    public function findAndModify($condition, $update, $options = [])
    {

        $document = array_merge(['findAndModify' => $this->collectionName], $options);

        if (!empty($condition)) {
            $options['query'] = $condition;
        }

        if (!empty($update)) {
            $options['update'] = $update;
        }

        foreach (['fields', 'query', 'sort', 'update'] as $name) {
            if (isset($options[$name])) {
                $document[$name] = (object) $options[$name];
            }
        }

        $this->document=$document;

        $cursor = $this->execute();

        $result = current($cursor->toArray());

        if (!isset($result['value'])) {
            return null;
        }

        return $result['value'];

    }



    public function findOneAndDelete($condition, $options=[]) {

    }

    public function findOneAndUpdate($condition, $update, $options = []) {

    }






    /**
     * 查找字段
     * @param array $fields
     * return MongoDB object
     */
    public function select($fields=[]){
        $this->select=$fields;
        return $this;
    }

    /**
     * @param int $limit
     * @return $this
     */
    public function limit($limit){
        $this->limit=$limit;
        return $this;
    }

    /**
     * Sets the OFFSET part of the query.
     * @param integer $offset the offset. Use null or negative value to disable offset.
     * @return $this the query object itself
     */
    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }


    /**
     * Normalizes fields list for the MongoDB select composition.
     * @param array|string $fields raw fields.
     * @return array normalized select fields.
     */
    public function buildSelectFields($fields)
    {
        $selectFields = [];
        foreach ((array)$fields as $key => $value) {
            if (is_int($key)) {
                $selectFields[$value] = true;
            } else {
                $selectFields[$key] = is_scalar($value) ? (bool)$value : $value;
            }
        }
        return $selectFields;
    }

    /**
     * @param \MongoDB\Driver\Cursor $cursor Mongo cursor instance to fetch data from.
     * @param bool $all whether to fetch all rows or only first one.
     * @return array|bool result.
     * @see Query::fetchRows()
     */
    public function fetchRows($cursor, $all=true)
    {
        $result = [];
        if ($all) {
            foreach ($cursor as $row) {
                $result[] = $row;
            }
        } else {
            if ($row = current($cursor->toArray())) {
                $result = $row;
            } else {
                $result = false;
            }
        }

        return $result;
    }



    /**
     * 设置操作条件.
     * @param array $condition 操作条件
     *         $condition=[
     *                  'status'=>['$gt'=>1],   // >1
     *                  'name'=>'tom',          // =tom
     *                  'age'=>['$lt'=>12]    // <12
     *                  'content'=>['$regex'=>'hello'] 查询content字段中关键字hello
     *                 ]
     * 条件操作符：$gt : > ,$lt : < , $gte: >= ,$lte: <= , $ne : !=、<> , $in : in , $nin: not in , $all: all , $not,$regex:
     * @return MongoDB object
     * @throws Exception on failure
     */
    protected function buildCondition($condition){
        if (!is_array($condition)) {
            throw new \Exception('Condition should be an array.');

        } elseif (empty($condition)) {
            $this->document=[];
        }else{

            $this->document=$condition;
        }

    }

    /** Returns a single document.
     * @param $options   ['id' => 1, 'age' => -1]   1:asc 正序  -1:倒序
     * @return $this
     * @throws \Exception
     */
    public function order_by($options){
        if (!is_array($options)) {
            throw new \Exception('Where should be an array.');

        } elseif (empty($options)) {
            return $this;
        }

        $this->orderBy=$options;

        return $this;
    }

    /**
     * Updates the rows, which matches given criteria by given data. 根据条件更新数据
     * Note: for "multi" mode Mongo requires explicit strategy "$set" or "$inc"
     * to be specified for the "newData". If no strategy is passed "$set" will be used.
     * @param array $condition description of the objects to update.
     * @param array $newData the object with which to update the matching records.
     * @param array $options list of options in format: optionName => optionValue.
     * @return int|bool number of updated documents or whether operation was successful.
     * @throws Exception on failure.
     */
    public function update($condition, $newData, $options = [])
    {
        $batchOptions = [];
        foreach (['bypassDocumentValidation'] as $name) {
            if (isset($options[$name])) {
                $batchOptions[$name] = $options[$name];
                unset($options[$name]);
            }
        }

        $this->document = [];
        $this->addUpdate($condition, $newData, $options);
        $_result = $this->executeBatch($batchOptions);

        $writeResult=$_result['result'];

        return $writeResult->getModifiedCount() + $writeResult->getUpsertedCount();
    }



    public function updateMany($filter,$update,$options = []) {

    }


    /**
     * Adds the update operation to the batch command.
     * @param array $condition filter condition
     * @param array $document data to be updated
     * @param array $options update options.
     * @return $this self reference.
     * @see executeBatch()
     */
    public function addUpdate($condition, $document, $options = [])
    {
        $options = array_merge(
            [
                'multi' => true,
                'upsert' => false,
            ],
            $options
        );

        if ($options['multi']) {
            $keys = array_keys($document);
            if (!empty($keys) && strncmp('$', $keys[0], 1) !== 0) {
                $document = ['$set' => $document];
            }
        }

        $this->document[] = [
            'type' => 'update',
            'condition' => $condition,
            'document' => $document,
            'options' => $options,
        ];
        return $this;
    }

    /**
     * Update the existing database data, otherwise insert this data
     * @param array|object $data data to be updated/inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return \MongoId updated/new record id instance.
     * @throws Exception on failure.
     */
    public function save($data, $options = [])
    {
        if (empty($data['_id'])) {
            return $this->insert($data, $options);
        } else {
            $id = $data['_id'];
            unset($data['_id']);
            $this->update(['_id' => $id], ['$set' => $data], ['upsert' => true]);
            return is_object($id) ? $id : new ObjectID($id);
        }
    }

    /**
     * Removes data from the collection. 根据条件删除数据
     * @param array $condition description of records to remove.
     * @param array $options list of options in format: optionName => optionValue.
     * @return int|bool number of updated documents or whether operation was successful.
     * @throws Exception on failure.
     */
    public function remove($condition = [], $options = [])
    {
        $options = array_merge(['limit' => 0], $options);

        $batchOptions = [];
        foreach (['bypassDocumentValidation'] as $name) {
            if (isset($options[$name])) {
                $batchOptions[$name] = $options[$name];
                unset($options[$name]);
            }
        }

        $this->document = [];
        $this->addDelete($condition, $options);
        $_result = $this->executeBatch($batchOptions);

        $writeResult=$_result['result'];
        return $writeResult->getDeletedCount();
    }

    /**
     * Adds the delete operation to the batch command.
     * @param array $condition filter condition.
     * @param array $options delete options.
     * @return $this self reference.
     * @see executeBatch()
     */
    public function addDelete($condition, $options = [])
    {
        $this->document[] = [
            'type' => 'delete',
            'condition' => $condition,
            'options' => $options,
        ];
        return $this;
    }

    /**
     * Counts records in this collection.
     * @param array $condition query condition
     * @param array $options list of options in format: optionName => optionValue.
     * @return int records count.
     * @since 2.1
     */
    public function count($condition = [], $options = [])
    {
        $document = ['count' => $this->collectionName];

        if (!empty($condition)) {
            $document['query'] = (object) $condition;
        }

        $this->document= array_merge($document, $options);

        $cursor = $this->execute();

        $result = current($cursor->toArray());

//        if (!isset($result['values']) || !is_array($result['values'])) {
//            return false;
//        }

//        if (!isset($result['n']) || !is_array($result['n'])) {
//            return false;
//        }
        /*
         * array(3) {
              ["waitedMS"]=>
              int(0)
              ["n"]=>
              int(5)
              ["ok"]=>
              float
         * */

        return $result['n'];
    }

    /**
     * Returns a list of distinct values for the given column across a collection.
     * @param string $fieldName column to use.
     * @param array $condition query parameters.
     * @param array $options list of options in format: optionName => optionValue.
     * @return array|bool array of distinct values, or "false" on failure.
     * @throws Exception on failure.
     */
    public function distinct($fieldName, $condition = [], $options = [])
    {
        $document = array_merge(
            [
                'distinct' => $this->collectionName,
                'key' => $fieldName,
            ],
            $options
        );

        if (!empty($condition)) {
            $document['query'] = $condition;
        }

        $this->document=$document;

        $cursor = $this->execute();

        $result = current($cursor->toArray());

        if (!isset($result['values']) || !is_array($result['values'])) {
            return false;
        }

        return $result['values'];
    }


    /**
     * Returns the list of defined indexes.
     * @return array list of indexes info.
     * @param array $options list of options in format: optionName => optionValue.
     * @since 2.1
     */
    public function listIndexes($options = [])
    {
        $this->document=array_merge(['listIndexes' => $this->collectionName], $options);

        $cursor = $this->execute();

        return $cursor->toArray();
    }

    /**
     * Creates several indexes at once.
     * Example:
     *
     * ```php
     *
     * MongoDB::getinstance()->collection('mytest')->createIndexes([
     *     [
     *         'key' => ['name'],
     *     ],
     *     [
     *         'key' => [
     *             'email' => 1,
     *             'address' => -1,
     *         ],
     *         'name' => 'my_index', 索引名称
     *         'unique' =>true , 设置唯一
     *     ],
     * ]);
     * ```
     *
     * @param array $indexes indexes specification, each index should be specified as an array.
     * The main options are:
     *
     * - keys: array, column names with sort order, to be indexed. This option is mandatory.
     * - unique: bool, whether to create unique index.
     * - name: string, the name of the index, if not set it will be generated automatically.
     * - background: bool, whether to bind index in the background.
     * - sparse: bool, whether index should reference only documents with the specified field.
     *
     * See [[https://docs.mongodb.com/manual/reference/method/db.collection.createIndex/#options-for-all-index-types]]
     * for the full list of options.
     * @return bool whether operation was successful.
     * @since 2.1
     */
    public function createIndexes($indexes)
    {
        $normalizedIndexes = [];

        foreach ($indexes as $index) {
            if (!isset($index['key'])) {
                throw new \Exception('"key" is required for index specification');
            }

            $index['key'] = $this->buildSortFields($index['key']);

            if (!isset($index['ns'])) {
                if ($this->database === null) {
                    $this->database = $this->getDefaultDb();
                }
                $index['ns'] = $this->database . '.' . $this->collectionName;
            }

            if (!isset($index['name'])) {
                $index['name'] = $this->generateIndexName($index['key']);
            }

            $normalizedIndexes[] = $index;
        }

        $this->document= [
            'createIndexes' => $this->collectionName,
            //'keys'  => $normalizedIndexes,
            'indexes' => $normalizedIndexes,
        ];
			
		//var_export($this->document);exit;	
			
        $result = current($this->execute()->toArray());
        return $result['ok'] > 0;
    }

    /**
     * Creates an index on the collection and the specified fields.
     * @param array|string $columns column name or list of column names.
     * If array is given, each element in the array has as key the field name, and as
     * value either 1 for ascending sort, or -1 for descending sort.
     * You can specify field using native numeric key with the field name as a value,
     * in this case ascending sort will be used.
     * For example:
     *
     * ```php
     * [
     *     'name',
     *     'status' => -1,
     * ]
     * ```
     *
     * @param array $options list of options in format: optionName => optionValue.
     *  $options['name']='my_index_name', 设置索引名称
     *  $options['unique']=true, // 设置唯一索引
     *  ...
     * @throws Exception on failure.
     * @return bool whether the operation successful.
     */
    public function createIndex($columns, $options = [])
    {
        $index = array_merge(['key' => $columns], $options);
        return $this->createIndexes([$index]);
    }

    /**
     * Normalizes fields list for the MongoDB sort composition.
     * @param array|string $fields raw fields.
     * @return array normalized sort fields.
     */
    public function buildSortFields($fields)
    {
        $sortFields = [];
        foreach ((array)$fields as $key => $value) {
            if (is_int($key)) {
                $sortFields[$value] = +1;
            } else {
                if ($value === SORT_ASC) {
                    $value = +1;
                } elseif ($value === SORT_DESC) {
                    $value = -1;
                }
                $sortFields[$key] = $value;
            }
        }
        return $sortFields;
    }

    /**
     * Generates index name for the given column orders.
     * @param array $columns columns with sort order.
     * @return string index name.
     */
    private function generateIndexName($columns)
    {
        $parts = [];
        foreach ($columns as $column => $order) {
            $parts[] = $column . '_' . $order;
        }
        return implode('_', $parts);
    }

    /**
     * Drops collection indexes by name.
     * @param string $indexes wildcard for name of the indexes to be dropped.
     * You can use `*` to drop all indexes.
     * @return int count of dropped indexes.
     */
    public function dropIndexes($indexes)
    {
        $this->document= [
            'dropIndexes' => $this->collectionName,
            'index' => $indexes,
        ];

        return current($this->execute()->toArray());
    }



    /**
     * Drop indexes for specified column(s).
     * @param string|array $columns column name or list of column names.
     * If array is given, each element in the array has as key the field name, and as
     * value either 1 for ascending sort, or -1 for descending sort.
     * Use value 'text' to specify text index.
     * You can specify field using native numeric key with the field name as a value,
     * in this case ascending sort will be used.
     * For example:
     *
     * ```php
     * [
     *     'name',
     *     'status' => -1,
     *     'description' => 'text',
     * ]
     * ```
     *
     * @throws Exception on failure.
     * @return bool whether the operation successful.
     */
    public function dropIndex($columns)
    {
        $existingIndexes = $this->listIndexes();

        $indexKey = $this->buildSortFields($columns);

        foreach ($existingIndexes as $index) {
            if ($index['key'] == $indexKey) {
                $this->dropIndexes( $index['name']);
                return true;
            }
        }

        throw new Exception('Index to be dropped does not exist.');
    }

    /**
     * Drops all indexes for this collection.
     * @throws Exception on failure.
     * @return int count of dropped indexes.
     */
    public function dropAllIndexes()
    {
        $result = $this->dropIndexes('*');
        return $result['nIndexesWas'];
    }

    /**
     * Performs aggregation using Mongo Aggregation Framework.
     * @param array $pipelines list of pipeline operators.
     * @param array $options optional parameters.
     * @return array the result of the aggregation.
     * @throws Exception on failure.
     */
    public function aggregate($pipelines, $options = [])
    {
//        foreach ($pipelines as $key => $pipeline) {
//            if (isset($pipeline['$match'])) {
//                $pipelines[$key]['$match'] = ($pipeline['$match']);
//            }
//        }

        $document = array_merge(
            [
                'aggregate' => $this->collectionName,
                'pipeline' => $pipelines,
                //'allowDiskUse' => false,
            ],
            $options
        );

        $this->document=$document;

        $cursor = $this->execute();

        $result = current($cursor->toArray());

        return $result['result'];
    }

    /**
     * Performs aggregation using Mongo "group" command.
     * @param mixed $keys fields to group by. If an array or non-code object is passed,
     * it will be the key used to group results. If instance of [[\MongoDB\BSON\Javascript]] passed,
     * it will be treated as a function that returns the key to group by.
     * @param array $initial Initial value of the aggregation counter object.
     * @param \MongoDB\BSON\Javascript|string $reduce function that takes two arguments (the current
     * document and the aggregation to this point) and does the aggregation.
     * Argument will be automatically cast to [[\MongoDB\BSON\Javascript]].
     * @param array $options optional parameters to the group command. Valid options include:
     *  - condition - criteria for including a document in the aggregation.
     *  - finalize - function called once per unique key that takes the final output of the reduce function.
     * @return array the result of the aggregation.
     * @throws Exception on failure.
     */
    public function group($keys, $initial, $reduce, $options = [])
    {
        if (!($reduce instanceof Javascript)) {
            $reduce = new Javascript((string) $reduce);
        }

        if (isset($options['condition'])) {
            $options['cond'] = $options['condition'];
            unset($options['condition']);
        }

        if (isset($options['finalize'])) {
            if (!($options['finalize'] instanceof Javascript)) {
                $options['finalize'] = new Javascript((string) $options['finalize']);
            }
        }

        if (isset($options['keyf'])) {
            $options['$keyf'] = $options['keyf'];
            unset($options['keyf']);
        }
        if (isset($options['$keyf'])) {
            if (!($options['$keyf'] instanceof Javascript)) {
                $options['$keyf'] = new Javascript((string) $options['$keyf']);
            }
        }

        $document = [
            'group' => array_merge(
                [
                    'ns' => $this->collectionName,
                    'key' => $keys,
                    'initial' => $initial,
                    '$reduce' => $reduce,
                ],
                $options
            )
        ];

        $this->document=$document;

        $cursor = $this->execute();

        $result = current($cursor->toArray());

        return $result['retval'];
    }



    /**
     * Returns write concern for this command.
     * @return WriteConcern|null write concern to be used in this command.
     */
    public function getWriteConcern()
    {
        if ($this->_writeConcern !== null) {
            if (is_scalar($this->_writeConcern)) {
                $this->_writeConcern = new \Mongodb\Driver\WriteConcern($this->_writeConcern);
            }
        }
        return $this->_writeConcern;
    }

    /**
     * Sets write concern for this command.
     * @param WriteConcern|int|string|null $writeConcern write concern, it can be an instance of [[WriteConcern]]
     * or its scalar mode value, for example: `majority`.
     * @return $this self reference
     */
    public function setWriteConcern($writeConcern)
    {
        $this->_writeConcern = $writeConcern;
        return $this;
    }

    /**
     * 生成用户ID
     * */
    public function getAutoId_mongodb($agentId=1,$nServerId=1)
    {
        try
        {
            $condition  = array('channel' => intval($agentId),'serverId' => intval($nServerId));
            $update = array('$inc' => array('incId' => 1));
            $options['fields'] = array('_id' => 0,'incId' => 1);
            $options['new']=true;
            $options['upsert']=true;
            $ret=$this->collection('user_auto_ids')->findAndModify($condition,$update,$options);

            if($ret['incId'] < 100000) {
                return ($agentId * 1000).($nServerId * 1000).str_pad($ret['incId'],6,"0",STR_PAD_LEFT);
            }
            return ($agentId * 1000).($nServerId * 1000).$ret['incId'];
        }catch (\MongoException $ex)
        {
            $this->show_error(" getAutoId_mongodb Exception : ".$ex->getMessage());
        }
    }

    public function __destruct()
    {
        //$this->manager->dis
    }

}
