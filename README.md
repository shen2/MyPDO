MyPDO
=====
MyPDO是一个用php编写的依赖PDO的ORM类(Object Rentied Model)，通过它你可以用面向对象的方式来写sql语句，也可以以面向对象的方式来操作每一条数据库记录。

MyPDO基于Zend Framework 1.x版本中的Zend_Db组件修改而来，经过彻底的修改和优化之后，去掉了对Zend Framework其他组件的依赖。能达到很高的性能，同时给你的编程带来极大的便利。

## 特性

性能方面，MyPDO会将多个sql语句拼合在一起执行query操作，由于php会在第一条SQL语句执行完成之后就返回，因此能够在同步编程模式中实现类似于“伪异步”的效果，从而减少mysql阻塞时间，大大提高整体性能。
同时，MyPDO充分利用php SPL中的ArrayObject和SplFixedArray，使对于数据对象的访问、迭代速度相比Zend_Db有数量级的提升。

编程方式上，MyPDO倡导流式接口(fluence interface)，并且最小化代码，去掉所有不必要的中间变量和赋值过程，并希望让代码更接近自然语言。希望MyPDO能让你的php编程变得更有条理，更快乐。

MyPDO由沈振宇开发，先后用于图虫网和多说网的服务器端php程序，经过2年多生产环境的考验，可靠稳定。

## 局限性
* MyPDO虽然有beginTransection()这样的类，但并不支持事务操作（因为作者所涉及的业务都不需要事务操作）
* MyPDO由于将所有数据表都抽象成静态类，因此无法应对需要做水平Sharding的数据表 (在多说上使用的是经过改进的MyPDO，从而使它支持Sharding)
* MyPDO目前不支持主从读写分离，但是会考虑以后支持

## 环境和依赖
* php 5.3+，建议php5.4.11以上版本 (已知 php5.4.9 的mysqlnd存在bug，会导致出错)
* PDO扩展，建议安装mysqlnd扩展
* 建议使用autoload机制

## 类的基本说明
* MyPDO 基本常量声明
* MyPDO\Adapter 维护PDO connection的适配器
* MyPDO\DataObject 核心类，调用静态方法时是对数据表的操作，调用实例方法是对数据记录的操作
* MyPDO\Select.php SQL语句的抽象，可以流式接口编写SQL语句
* MyPDO\TableSelect Select对象的派生
* MyPDO\Statement 储存sql查询语句的中间结果，可以直接进行foreach迭代或者count()
* MyPDO\Expr SQL语句中的特殊表达式，比如 now()，n + 1 这样不需要经过转义的表达式
* MyPDO\Profiler 记录sql运行执行情况，可以只在development模式下使用，生产环境不加载

## 初始化方法
    <?php 
    require 'MyPDO.php';
    require 'MyPDO/Adapter.php';
    require 'MyPDO/DataObject.php';
    require 'MyPDO/Select.php';
    require 'MyPDO/TableSelect.php';
    require 'MyPDO/Statement.php';
    require 'MyPDO/Expr.php';
    
    $configs = array(
        'host'		=> 'localhost',	//	主机名
        'username'	=> 'username',	//	用户名
        'password'	=> 'password',	//	密码
        'dbname'	=> 'dbname',	//	数据库名
        'port'		=> 3306,		//	端口号，可省略
        'profiler'	=> false,		//	是否启用Profiler，默认false
        'persistent'=> true,		//	是否持久化连接
        'charset'	=> 'utf8',		//	默认字符集
        'fetchMode'	=> PDO::FETCH_ASSOC,//PDO的默认fetch模式
        );
      
    $db = new MyPDO\Adapter($configs);
    MyPDO\DataObject::setDefaultAdapter($db);
    ?>
出于性能的考虑，即使你使用了autoload机制，仍然建议你显式地主动require以上那些php文件，这样可以减少发动autoload的次数，避免不必要的性能浪费。

## DataObject类的派生
DataObject其实是一个抽象的基类，真正使用的时候需要将它派生成自己的类。

    <?php 
    class User extends MyPDO\DataObject{
        protected static $_schema = 'dbname';				// 数据库的名字，默认是null，代表当前数据库
        protected static $_name = 'pre_users';				// 数据表的名字
        protected static $_primary = array('user_id');		// 主键字段，必须是数组
        protected static $_sequence = true;					// 主键是否是自增的
        protected static $_defaultValues = array(
        );	// 默认数据，可以省略
    
        //	你自己的方法...
    }
    ?>

如果你的数据表是双重主键，并且第二重主键是自增的，可以写成：

    protected static $_primary = array('site_id', 'user_id');
    protected static $_identity = 1;				//	1代表$_primary[1]，即’user_id‘是自增主键

## MyPDO\Select 的用法
通常，我们通过已经派生的MyPDO\DataObject类的静态方法select()来创建 MyPDO\Select 实例。

    $select = User::select();
    // select * from `pre_users`;

### where()
为了避免出现sql注入漏洞，所有包含变量的where条件，都应使用?来进行转义。

    $select = User::select()
        ->where('name like ?', '小钢炮');
    // select * from `pre_users` where name like '小钢炮';

如果有多个where条件，以and相连，直接调用多次where()就可以了。

    $select = User::select()
        ->where('gender = ?', 'male')
        ->where('age > ?', 40);
    // select * from `pre_users` where gender = 'male' and age > 40;

### order()
order()方法的参数就是排序字段名，asc/desc直接写在字符串里。

    $select = User::select()
        ->order('created_at desc');
    // select * from `pre_users` order by created_at desc;

如果有多个order字段，直接调用多次order函数就可以了。

    $select = User::select()
        ->order('name asc')
        ->order('email asc');
    // select * from `pre_users` order by name asc, email asc;

### limit()
第一个参数是limit的字段数，第二个参数是offset(默认是0,可省略)

    $select = User::select()
        ->order('name asc')
        ->order('email asc')
        ->limit(10, 30);
    // select * from `pre_users` order by name asc, email asc limit 10 offset 30;

### 指定 select 的字段
如果你想获得指定的字段，而不需要所有字段(*)，可以使用selectCol函数，比如：

    $select = User::selectCol('count(*)');
    // select count(*) from `pre_users`;

如果是多个字段，可以写成：

    $select = User::selectCol(array('user_id', 'name', 'email'));
    // select user_id, name, email from `pre_users`;

如果给字段起别名，可以写成：

    $select = User::selectCol(array('id' => 'user_id', 'name', 'email'));
    // select user_id as id, name, email from `pre_users`;

### join()
例如现在有 pre_users 表和 pre_posts 表，我们要把posts表的查询结果和users表join。可以写成：

    Post::select(true)
    	->joinInner('pre_users', 'pre_users.user_id = pre_posts.author_id', array('uid'=>'user_id', 'date'=>'updated'))
    	->where(...)
    // select `pre_posts`.*, `pre_users`.user_id as id, `pre_users`.updated as date from `pre_posts` inner join pre_users on pre_users.user_id = pre_posts.author_id;

### assemble()
有时候你不确定写出的MyPDO\Select对象在执行的时候会转换成什么SQL语句，可以使用assemble()方法预览SQL语句

    echo $select->assemble();

## fetch封装方法
每个数据表类有四种种常用的调用方式：fetchAll(), fetchRow(), fetchOne() 和 find()

### fetchAll()
    $userList = User::select()
        ->where('created_at > ?', '2012-12-21')
        ->order('image_count desc')
        ->limit(5)
        ->fetchAll();

返回值是一个MyPDO\Statement，可以直接进行foreach迭代或者count()

### fetchRow()
获取一行的方法

    $user = User::select()
        ->where('email_hash = md5(?)', 'd269c7b5b75e3f6fd794e68e889b5daa')
        ->fetchRow();

不需要额外写limit(1)，因为fetchRow()方法会自动给sql语句增加 limit 1

### fetchOne()
如果结果集是单行单列的，用fetchOne()可以直接得到这个值

    $count = User::selectCol('count(*)')
        ->where('created_at > ?', '2012-12-21')
        ->fetchOne();

### find()
主键查询肯定是用得最广泛的，使用find()方法可以简化主键查询的过程。

    $user = User::find(40)->current();
这样就可以查询到主键为40的user对象，如果记录不存在，返回值是null。

如果想通过多个ID一次查询多条记录，可以写成：

    $userList = User::find(array(1,2,3,4,5));
返回值是一个MyPDO\Statement，可以直接foreach迭代。注意，结果集中的对象顺序未必和find的参数相同，结果集中的对象数量也可能小于find的参数。

find()还支持多重主键的查询：

    $relationship = Friendship::find(123,456)->current();

## MyPDO\Statement
$select->fetchAll()的返回值是一个MyPDO\Statement对象，这个对象未必含有查询的结果集，而有可能结果还在数据库中进行处理。

只有当你主动调用count(), fetch()，或者对它进行foreach迭代的时候，程序才会强制等待mysql数据库返回全部查询结果。

如果这给你带来了困扰，可以使用fetch()方法，获得真正的结果数据集(一个SplFixedArray对象):

    $fixedArray = $select->fetchAll()->fetch();
