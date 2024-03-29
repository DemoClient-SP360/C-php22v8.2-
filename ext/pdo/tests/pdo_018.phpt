--TEST--
PDO Common: serializing
--EXTENSIONS--
pdo
--SKIPIF--
<?php
$dir = getenv('REDIR_TEST_DIR');
if (false == $dir) die('skip no driver');
require_once $dir . 'pdo_test.inc';
PDOTest::skip();
?>
--FILE--
<?php
if (getenv('REDIR_TEST_DIR') === false) putenv('REDIR_TEST_DIR='.__DIR__ . '/../../pdo/tests/');
require_once getenv('REDIR_TEST_DIR') . 'pdo_test.inc';
$db = PDOTest::factory();

class TestBase implements Serializable
{
    public    $BasePub = 'Public';
    protected $BasePro = 'Protected';
    private   $BasePri = 'Private';

    function serialize()
    {
        $serialized = array();
        foreach($this as $prop => $val) {
            $serialized[$prop] = $val;
        }
        $serialized = serialize($serialized);
        echo __METHOD__ . "() = '$serialized'\n";
        return $serialized;
    }

    function unserialize($serialized)
    {
        echo __METHOD__ . "($serialized)\n";
        foreach(unserialize($serialized) as $prop => $val) {
            $this->$prop = '#'.$val;
        }
        return true;
    }
}

class TestDerived extends TestBase
{
    public    $BasePub    = 'DerivedPublic';
    protected $BasePro    = 'DerivdeProtected';
    public    $DerivedPub = 'Public';
    protected $DerivedPro = 'Protected';
    private   $DerivedPri = 'Private';

    function serialize()
    {
        echo __METHOD__ . "()\n";
        return TestBase::serialize();
    }

    function unserialize($serialized)
    {
        echo __METHOD__ . "()\n";
        return TestBase::unserialize($serialized);
    }
}

class TestLeaf extends TestDerived
{
}

$db->exec('CREATE TABLE classtypes018(id int NOT NULL PRIMARY KEY, name VARCHAR(20) NOT NULL UNIQUE)');
$db->exec("INSERT INTO classtypes018 VALUES(0, 'stdClass')");
$db->exec("INSERT INTO classtypes018 VALUES(1, 'TestBase')");
$db->exec("INSERT INTO classtypes018 VALUES(2, 'TestDerived')");

switch ($db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
  case 'dblib':
    // environment settings can influence how the table is created if specifics are missing
    // https://msdn.microsoft.com/en-us/library/ms174979.aspx#Nullability Rules Within a Table Definition
    $sql = 'CREATE TABLE test018(id int NOT NULL PRIMARY KEY, classtype int NULL, val VARCHAR(255) NULL)';
    break;
  default:
    $sql = 'CREATE TABLE test018(id int NOT NULL PRIMARY KEY, classtype int, val VARCHAR(255))';
    break;
}
$db->exec($sql);

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

var_dump($db->query('SELECT COUNT(*) FROM classtypes018')->fetchColumn());
var_dump($db->query('SELECT id, name FROM classtypes018 ORDER by id')->fetchAll(PDO::FETCH_COLUMN|PDO::FETCH_UNIQUE));

$objs = array();
$objs[0] = new stdClass;
$objs[1] = new TestBase;
$objs[2] = new TestDerived;
$objs[3] = new TestLeaf;

$stmt = $db->prepare('SELECT id FROM classtypes018 WHERE name=:cname');
$stmt->bindParam(':cname', $cname);

$ctypes = array();

foreach($objs as $obj)
{
    $cname = get_class($obj);
    $ctype = NULL; /* set default for non stored class name */
    $stmt->execute();
    $stmt->bindColumn('id', $ctype);
    $stmt->fetch(PDO::FETCH_BOUND);
    $ctypes[$cname] = $ctype;
}

echo "===TYPES===\n";
var_dump($ctypes);

unset($stmt);

echo "===INSERT===\n";
$stmt = $db->prepare('INSERT INTO test018 VALUES(:id, :classtype, :val)');
$stmt->bindParam(':id', $idx);
$stmt->bindParam(':classtype', $ctype);
$stmt->bindParam(':val', $val);

foreach($objs as $idx => $obj)
{
    $ctype = $ctypes[get_class($obj)];
    if (method_exists($obj, 'serialize'))
    {
        $val = $obj->serialize();
    }
    else
    {
        $val = '';
    }
    $stmt->execute();
}

unset($stmt);

echo "===DATA===\n";
$res = $db->query('SELECT test018.val FROM test018')->fetchAll(PDO::FETCH_COLUMN);

switch ($db->getAttribute(PDO::ATTR_DRIVER_NAME)) {
    case 'dblib':
        // map whitespace (from early TDS versions) to empty string so the test doesn't diff
        if ($res[0] === ' ') {
            $res[0] = '';
        }
        break;

    case 'oci':
        // map NULL to empty string so the test doesn't diff
        if ($res[0] === null) {
            $res[0] = '';
        }
        break;
}
var_dump($res);

echo "===FAILURE===\n";
try
{
    $db->query('SELECT classtypes018.name AS name, test018.val AS val FROM test018 LEFT JOIN classtypes018 ON test018.classtype=classtypes018.id')->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE|PDO::FETCH_SERIALIZE, 'TestLeaf', array());
}
catch (PDOException $e)
{
    echo 'Exception:';
    echo $e->getMessage()."\n";
}

echo "===COUNT===\n";
var_dump($db->query('SELECT COUNT(*) FROM test018 LEFT JOIN classtypes018 ON test018.classtype=classtypes018.id WHERE (classtypes018.id IS NULL OR classtypes018.id > 0)')->fetchColumn());

echo "===DATABASE===\n";
$stmt = $db->prepare('SELECT classtypes018.name AS name, test018.val AS val FROM test018 LEFT JOIN classtypes018 ON test018.classtype=classtypes018.id WHERE (classtypes018.id IS NULL OR classtypes018.id > 0)');

$stmt->execute();
var_dump($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "===FETCHCLASS===\n";
$stmt->execute();
var_dump($stmt->fetchAll(PDO::FETCH_CLASS|PDO::FETCH_CLASSTYPE|PDO::FETCH_SERIALIZE, 'TestLeaf'));


?>
--CLEAN--
<?php
require_once getenv('REDIR_TEST_DIR') . 'pdo_test.inc';
$db = PDOTest::factory();
PDOTest::dropTableIfExists($db, "test018");
PDOTest::dropTableIfExists($db, "classtypes018");
?>
--EXPECTF--
Deprecated: %s implements the Serializable interface, which is deprecated. Implement __serialize() and __unserialize() instead (or in addition, if support for old PHP versions is necessary) in %s on line %d

Deprecated: %s implements the Serializable interface, which is deprecated. Implement __serialize() and __unserialize() instead (or in addition, if support for old PHP versions is necessary) in %s on line %d

Deprecated: %s implements the Serializable interface, which is deprecated. Implement __serialize() and __unserialize() instead (or in addition, if support for old PHP versions is necessary) in %s on line %d
string(1) "3"
array(3) {
  [0]=>
  string(8) "stdClass"
  [1]=>
  string(8) "TestBase"
  [2]=>
  string(11) "TestDerived"
}
===TYPES===
array(4) {
  ["stdClass"]=>
  string(1) "0"
  ["TestBase"]=>
  string(1) "1"
  ["TestDerived"]=>
  string(1) "2"
  ["TestLeaf"]=>
  NULL
}
===INSERT===
TestBase::serialize() = 'a:3:{s:7:"BasePub";s:6:"Public";s:7:"BasePro";s:9:"Protected";s:7:"BasePri";s:7:"Private";}'
TestDerived::serialize()
TestBase::serialize() = 'a:5:{s:7:"BasePub";s:13:"DerivedPublic";s:7:"BasePro";s:16:"DerivdeProtected";s:7:"BasePri";s:7:"Private";s:10:"DerivedPub";s:6:"Public";s:10:"DerivedPro";s:9:"Protected";}'
TestDerived::serialize()
TestBase::serialize() = 'a:5:{s:7:"BasePub";s:13:"DerivedPublic";s:7:"BasePro";s:16:"DerivdeProtected";s:7:"BasePri";s:7:"Private";s:10:"DerivedPub";s:6:"Public";s:10:"DerivedPro";s:9:"Protected";}'
===DATA===
array(4) {
  [0]=>
  string(0) ""
  [1]=>
  string(91) "a:3:{s:7:"BasePub";s:6:"Public";s:7:"BasePro";s:9:"Protected";s:7:"BasePri";s:7:"Private";}"
  [2]=>
  string(172) "a:5:{s:7:"BasePub";s:13:"DerivedPublic";s:7:"BasePro";s:16:"DerivdeProtected";s:7:"BasePri";s:7:"Private";s:10:"DerivedPub";s:6:"Public";s:10:"DerivedPro";s:9:"Protected";}"
  [3]=>
  string(172) "a:5:{s:7:"BasePub";s:13:"DerivedPublic";s:7:"BasePro";s:16:"DerivdeProtected";s:7:"BasePri";s:7:"Private";s:10:"DerivedPub";s:6:"Public";s:10:"DerivedPro";s:9:"Protected";}"
}
===FAILURE===

Deprecated: PDOStatement::fetchAll(): The PDO::FETCH_SERIALIZE mode is deprecated in %s on line %d
Exception:SQLSTATE[HY000]: General error: cannot unserialize class
===COUNT===
string(1) "3"
===DATABASE===
array(3) {
  [0]=>
  array(2) {
    ["name"]=>
    string(8) "TestBase"
    ["val"]=>
    string(91) "a:3:{s:7:"BasePub";s:6:"Public";s:7:"BasePro";s:9:"Protected";s:7:"BasePri";s:7:"Private";}"
  }
  [1]=>
  array(2) {
    ["name"]=>
    string(11) "TestDerived"
    ["val"]=>
    string(172) "a:5:{s:7:"BasePub";s:13:"DerivedPublic";s:7:"BasePro";s:16:"DerivdeProtected";s:7:"BasePri";s:7:"Private";s:10:"DerivedPub";s:6:"Public";s:10:"DerivedPro";s:9:"Protected";}"
  }
  [2]=>
  array(2) {
    ["name"]=>
    NULL
    ["val"]=>
    string(172) "a:5:{s:7:"BasePub";s:13:"DerivedPublic";s:7:"BasePro";s:16:"DerivdeProtected";s:7:"BasePri";s:7:"Private";s:10:"DerivedPub";s:6:"Public";s:10:"DerivedPro";s:9:"Protected";}"
  }
}
===FETCHCLASS===

Deprecated: PDOStatement::fetchAll(): The PDO::FETCH_SERIALIZE mode is deprecated in %s on line %d
TestBase::unserialize(a:3:{s:7:"BasePub";s:6:"Public";s:7:"BasePro";s:9:"Protected";s:7:"BasePri";s:7:"Private";})
TestDerived::unserialize()
TestBase::unserialize(a:5:{s:7:"BasePub";s:13:"DerivedPublic";s:7:"BasePro";s:16:"DerivdeProtected";s:7:"BasePri";s:7:"Private";s:10:"DerivedPub";s:6:"Public";s:10:"DerivedPro";s:9:"Protected";})
TestDerived::unserialize()
TestBase::unserialize(a:5:{s:7:"BasePub";s:13:"DerivedPublic";s:7:"BasePro";s:16:"DerivdeProtected";s:7:"BasePri";s:7:"Private";s:10:"DerivedPub";s:6:"Public";s:10:"DerivedPro";s:9:"Protected";})
array(3) {
  [0]=>
  object(TestBase)#%d (3) {
    ["BasePub"]=>
    string(7) "#Public"
    ["BasePro":protected]=>
    string(10) "#Protected"
    ["BasePri":"TestBase":private]=>
    string(8) "#Private"
  }
  [1]=>
  object(TestDerived)#%d (6) {
    ["BasePub"]=>
    string(14) "#DerivedPublic"
    ["BasePro":protected]=>
    string(17) "#DerivdeProtected"
    ["BasePri":"TestBase":private]=>
    string(8) "#Private"
    ["DerivedPub"]=>
    string(7) "#Public"
    ["DerivedPro":protected]=>
    string(10) "#Protected"
    ["DerivedPri":"TestDerived":private]=>
    string(7) "Private"
  }
  [2]=>
  object(TestLeaf)#%d (6) {
    ["BasePub"]=>
    string(14) "#DerivedPublic"
    ["BasePro":protected]=>
    string(17) "#DerivdeProtected"
    ["BasePri":"TestBase":private]=>
    string(8) "#Private"
    ["DerivedPub"]=>
    string(7) "#Public"
    ["DerivedPro":protected]=>
    string(10) "#Protected"
    ["DerivedPri":"TestDerived":private]=>
    string(7) "Private"
  }
}
