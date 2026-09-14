<?php
// SQLite in memory only; never boots site config or deletes site records.
define('YII_ENABLE_ERROR_HANDLER', false);
$vendor=(getenv('SKEEKS_APP_ROOT') ?: '/app').'/vendor';
require $vendor.'/autoload.php';
require $vendor.'/yiisoft/yii2/Yii.php';
$app=new yii\console\Application(['id'=>'native-cleanup-test','basePath'=>__DIR__,'vendorPath'=>$vendor,'extensions'=>[],
 'components'=>['db'=>['class'=>yii\db\Connection::class,'dsn'=>'sqlite::memory:']]]);
$checks=0;
$check=static function($ok,$message)use(&$checks){if(!$ok)throw new RuntimeException($message);++$checks;};
foreach(['','sx_'] as $prefix){
 $app->db->tablePrefix=$prefix;
 $table=$prefix.'cms_search_phrase';
 $app->db->createCommand("CREATE TABLE $table (id INTEGER PRIMARY KEY, created_at INTEGER)")->execute();
 $seed=static function(array $ages)use($app,$table){
  $app->db->createCommand()->delete($table)->execute();
  foreach($ages as $id=>$age)$app->db->createCommand()->insert($table,['id'=>$id,'created_at'=>time()-$age])->execute();
 };
 $count=static function()use($app,$table){return (int)(new yii\db\Query())->from($table)->count();};
 $service=new \skeeks\cms\search\services\PhraseCleanup(['batchSize'=>2]);
 $seed([1=>7200,2=>7200,3=>7200,4=>10]);
 $result=$service->run(3600);
 $check($result['deleted']===3 && $result['batches']===2 && $count()===1,'Batched deletion preserves recent rows');
 $check($service->run(3600)['deleted']===0,'Repeated cleanup is empty');
 $seed([1=>7200,2=>7200,3=>7200,4=>10]);
 $service->run(3600,static function($progress)use($app,$table){
  if($progress['batches']===1 && !(new yii\db\Query())->from($table)->where(['id'=>99])->exists()){
   $app->db->createCommand()->insert($table,['id'=>99,'created_at'=>time()-7200])->execute();
  }
 });
 $check((new yii\db\Query())->from($table)->where(['id'=>99])->exists(),'New rows remain outside the captured high-water mark');
 $seed([1=>7200,2=>7200,3=>7200,4=>10]);
 try{
  $service->run(3600,static function($progress){if($progress['batches']===1)throw new \skeeks\cms\job\exceptions\JobCancelledException('cancel');});
  throw new RuntimeException('Cancellation ignored');
 }catch(\skeeks\cms\job\exceptions\JobCancelledException $expected){$check($count()===2,'Cancellation stops between batches');}
 $seed([1=>7200,2=>10]);
 $zero=$service->run(0);
 $check(!$zero['enabled'] && $zero['deleted']===0 && $count()===2,'Zero-retention semantics preserved');
}
class NativeCleanupSettings extends yii\base\Model {
 public $phraseLiveTime=99999;
 public $cmsSite;
 public $cmsUser;
 public function getCallAttributes(){return ['phraseLiveTime'=>3600];}
 public function getSettings($cache=true){return $this->cmsSite && $this->cmsSite->id===2?['phraseLiveTime'=>1800]:[];}
 public function rules(){return [[['phraseLiveTime'],'integer']];}
}
class NativeCleanupReporter extends \skeeks\cms\job\runtime\JobReporter {
 public $result=[]; public $success=0; public $advanced=0; public $beats=0; public $cancel=false; public $stage; public $skipped=0;
 public function init(){}
 public function setStage(string $stage,?string $message=null):void{$this->stage=$stage;}
 public function setTotal(?int $total):void{}
 public function advance(int $by=1):void{$this->advanced+=$by;}
 public function countSuccess(int $by=1):void{$this->success+=$by;}
 public function countSkipped(int $by=1):void{$this->skipped+=$by;}
 public function heartbeat():void{++$this->beats;}
 public function isCancelled():bool{return $this->cancel;}
 public function setResult(array $result):void{$this->result=$result;}
}
$settings=new NativeCleanupSettings();
$app->set('cmsSearch',$settings);
$context=new class extends \skeeks\cms\job\runtime\JobContext{
 public function getSite(){return (object)['id'=>2];}
 public function getRun(){return (object)['cms_site_id'=>2];}
 // Legacy queued payloads must not execute any command.
 public function getPayload(){return ['command'=>'unrelated/command'];}
};
$seed([1=>1900,2=>1000]);
$reporter=new NativeCleanupReporter();
$handler=new \skeeks\cms\search\jobs\PhraseCleanupJobHandler();
$handler->run($context,$reporter);
$check($reporter->result['deleted']===1 && $reporter->success===1 && $reporter->advanced===1 && $reporter->beats>0,'Native handler reports actual deletes and heartbeat');
$check($settings->phraseLiveTime===99999 && $settings->cmsSite===null,'Run site settings do not leak into worker component');
$seed([1=>1900]);
$reporter=new NativeCleanupReporter();$reporter->cancel=true;
try{$handler->run($context,$reporter);throw new RuntimeException('Cancellation ignored');}
catch(\skeeks\cms\job\exceptions\JobCancelledException $expected){$check($count()===1,'Cancellation before deletion preserves records');}
$app->db->createCommand()->dropTable($table)->execute();
try{$handler->run($context,new NativeCleanupReporter());throw new RuntimeException('DB error swallowed');}
catch(yii\db\Exception $expected){++$checks;}
echo "PASS: $checks native cleanup checks\n";