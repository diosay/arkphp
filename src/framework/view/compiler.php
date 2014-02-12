<?php
namespace ark\view;
defined ( 'ARK' ) or exit ( 'access denied' );

/**
 * 表达式基类
 * @author jun
 *
 */
abstract class Expression{
	/**
	 * 获取表达式的节点类型。
	 * @return string
	 */
	public abstract function getNodeType();
}

class UnExpress{
	
}

/**
 * 二元运算表达式
 * @author jun
 *
 */
class BinaryExpression extends Expression{
	
	/**
	 * @var string
	 */
	private $_operator;
	/**
	 * @var Expression
	 */
	private $_left;
	/**
	 * @var Expression
	 */
	private $_right;
	
	public function __construct($operator,$left,$right){
		$this->_operator=$operator;
		$this->_left=$left;
		$this->_right=$right;
	}
	
	public function getNodeType(){
		return 'Binary';
	}
	
	public function getOperator(){
		return $this->_operator;
	}
	
	public function getLeft(){
		return $this->_left;
	}
	
	public function getRight(){
		return $this->_right;
	}
}


class StreamReader{
	private $_filename;
	private $_handle;
	public function __construct($filename){
		$this->_filename=$filename;
	}
	
	public function open(){
		$this->_handle=fopen($this->_filename, 'r');
	}
	
	public function seek($offset,$whence = null){
		return fseek($this->_handle, $offset,$whence);
	}
	
	public function read(&$buffer,$offset=NULL,$count=NULL){
		$temp=fgets($this->_handle,$length);
	}
	
	function readLine(&$buffer,$offset=NULL) {
		$count=0;
		while (!feof($this->_handle)){
			$char=fread($this->_handle, 1);
			if($char===chr(10)){
				break;
			}
			else if($char===chr(13)){
				if(!feof($this->_handle)){
					$char=fread($this->_handle, 1);
					if($char!==chr(10)){
						fseek($this->_handle, -1,SEEK_CUR);
					}
				}
				
				break;
			}
			else{
				$count++;
				if($offset){
					$buffer[$offset++]=$char;
				}
				else{
					$buffer[]=$char;
				}
			}
		}
		return $count;
	}
	
	
	public function endOfStream(){
		return feof($this->_handle);
	}
	
}

abstract class Parser{
	
	/**
	 * 处理完成，可以获取结果。
	 * @var int
	 */
	const PARSE_DONE=1;
	/**
	 * 未处理，请继续。
	 * @var int
	 */
	const PARSE_CONTINUE=2;
	/**
	 * 处理失败。
	 * @var int
	 */
	const PARSE_FAILED=3;
	
	/**
	 * @var Compiler
	 */
	private $_compiler;
	protected $result;
	function __construct($compiler){
		$this->_compiler=$compiler;
	}
	
	/**
	 * 获取处理结果。
	 * @return string
	 */
	public function getResult(){
		return $this->result;
	}
	
	/**
	 * 获取当前编译器。
	 * @return Compiler
	 */
	public function getCompiler(){
		return $this->_compiler;
	}
	
	/**
	 * 解析标记表达式。
	 * @param 要解析的表达式。
	 * @return int
	 */
	public function parse($expr){
		return self::PARSE_CONTINUE;
	}
	
}


class VarParser extends Parser{
	
	function parse($expr){
		if(!preg_match('/^(\$|\#)/', $expr)){
			return self::PARSE_CONTINUE;
		}
		//$expr=ark_substr($expr, 0,ark_strlen($expr)-1);
		$expr=preg_replace('/\s*\|\s*(\w+)/', '@@@@$1', $expr);
		
		$arr=preg_split('/@@@@/', $expr);
		
		if(count($arr)==1){
			$this->result= '<?php echo '. $this->getCompiler()->parseExpr($arr[0]) .'; ?>';
		}
		else if(count($arr)==2){
			$params=$this->getCompiler()->parseParams($arr[1]);
			if(count($params)>=1){
				$name=key($params);
				$this->result= '<?php echo $view->format(\''.$name.'\','.$params[$name].','. $this->getCompiler()->parseExpr($arr[0]) .'); ?>';
			}
			else{
				$this->result= '<?php echo '. $this->getCompiler()->parseExpr($arr[0]) .'; ?>';
			}
		}
		else{
			return self::PARSE_CONTINUE;
		}
		
		return parent::PARSE_DONE;
	}
}

class IfParser extends Parser{
	
	function parse($expr){
		$compiler=$this->getCompiler();
		if(preg_match('/^if\s*\(/', $expr)){
			$compiler->openBlock('if');
			$this->result= '<?php '. $compiler->parseExpr($expr) .'{ ?>';
			return parent::PARSE_DONE;
		}
		else if(preg_match('/^if\s/', $expr)){
			$compiler->openBlock('if');
			$this->result= '<?php if( '. $compiler->parseExpr($expr) .'){ ?>';
			return parent::PARSE_DONE;
		}
		else if(preg_match('/^elif\s*\(/', $expr)){
				
			if($compiler->getBlockCount('if')<=0){
				throw new \Exception('elif 必须与 if 配对使用');
			}
			$expr=preg_replace('/^elif\s/', '', $expr);
				
			$this->result= '<?php else if '. $compiler->parseExpr($expr) .'{ ?>';
			return parent::PARSE_DONE;
		}
		else if(preg_match('/^elif\s/', $expr)){
			
			if($compiler->getBlockCount('if')<=0){
				throw new \Exception('elif 必须与 if 配对使用');
			}
			$expr=preg_replace('/^elif\s/', '', $expr);
			
			$this->result= '<?php }else if( '. $compiler->parseExpr($expr) .'){ ?>';
			return parent::PARSE_DONE;
		}
		else if(preg_match('/^else\s*$/', $expr)){
				
			if($compiler->getBlockCount('if')<=0){
				throw new \Exception('elif 必须与 if 配对使用');
			}
				
			$this->result= '<?php }else{ ?>';
			return parent::PARSE_DONE;
		}
		else if(preg_match('/^\/\s*if/', $expr)){
				
			if($compiler->getBlockCount('if')<=0){
				throw new \Exception('/if 必须与 if 配对使用');
			}
			$compiler->closeBlock('if');
				
			$this->result= '<?php }?>';
			return parent::PARSE_DONE;
		}
		return parent::PARSE_CONTINUE;
	}
}

class ForParser extends Parser{

	function parse($expr){
		$compiler=$this->getCompiler();
		if(preg_match('/^for\s*\:/', $expr)){
			$compiler->openBlock('for');
			$expr=trim(preg_replace('/^for\s*\:/', '', $expr));
			
			$var='';
			$val='';
			if(preg_match('/^\w\s*\=/', $expr)){
				$params=$this->getCompiler()->parseParams($expr);
				if(count($params)<2){
					throw new \Exception('for 表达式必须有2个变量');
				}
				$var=key($params);
				$val=$params[$var];
				
				if(!isset($params['step'])){
					$params['step']=1;
				}
				$this->result= '<?php $__iteration__'.$var.'=0; for($'.$var.'='.$val.';$'. $var .'<'.$params['max'].';'.$var.'+='.$params['step'].'){$__iteration__'.$var.'+=1; ?>';
				
			}
			else{
				$index=0;
				$var=$this->getCompiler()->getWord($expr,$index);
				$expr=trim(ark_substr($expr, $index+1));
				$params=$this->getCompiler()->parseParams($expr);
				if(!isset($params['max'])){
					throw new \Exception('for 表达式未设置最大值。');
				}
				if(!isset($params['step'])){
					$params['step']=1;
				}
				$this->result= '<?php $__iteration__'.$var.'=0; for(;$'. $var .'<'.$params['max'].';'.$var.'+='.$params['step'].'){$__iteration__'.$var.'+=1; ?>';
			}
			
			return parent::PARSE_DONE;
		}
		else if(preg_match('/^\/\s*for/', $expr)){

			if($compiler->getBlockCount('for')<=0){
				throw new \Exception('/for 必须与  for 配对使用');
			}
			$compiler->closeBlock('for');

			$this->result= '<?php }?>';
			return parent::PARSE_DONE;
		}
		return parent::PARSE_CONTINUE;
	}
}

class Compiler{
	private $_tplPath;
	private $_ifCount=0;
	private $_line=0;
	private $_literal=FALSE;
	private $_blocks=array();
	protected $result='';
	public function __construct($tplPath){
		$this->_tplPath=$tplPath;
	}
	protected function isWord($char){
		//http://baike.baidu.com/link?url=hkBBhFd4DFKmDIiScGYBGbW_UOKZTMylauon8m-stwWaqN0aoOHneJQnI6w_SUvM
		return preg_match('/\w{1}/', $char);
	}
	
	/**
	 * 获取一个单词 \w
	 * @param unknown $buffer
	 * @param number $index
	 * @param unknown $count
	 * @throws \Exception
	 * @return Ambigous <string, unknown>
	 */
	public function getWord(&$buffer,&$index=0,$count=-1){
		$result='';
		if($count===-1){
			$count=ark_strlen($buffer);
		}
		
		for (;$index<$count;$index++){
			
			if(!$this->isWord($buffer[$index])){
				$index--;
				break;
			}
			else {
				$result.=$buffer[$index];
			}
		}
		if(ark_strlen($result)===0){
			throw new \Exception('错误的变量名');
		}
		//$index+=1;
		return $result;
	}
	protected function findPairRight(&$buffer,$index=0,$left,$right,$count=-1){
		if($count===-1){
			$count=ark_strlen($buffer);
		}
		$m=0;
		for ($i=$index;$i<$count;$i++){
			
			if($buffer[$i]=='\\'){
				$i++;
				continue;
			}
			
			if($buffer[$i]==$right && $m==0){
				return $i;
			}
			else if($buffer[$i]==$right){
				$m--;
			}
			else if($buffer[$i]==$left){
				$m++;
			}
		}
		return -1;
	}
	
	protected function getString(&$buffer,&$index=0,$count=-1){
		
		if($count===-1){
			$count=ark_strlen($buffer);
		}
		$result='';
		for (;$index<$count;$index++){
			
			if($buffer[$index]=='\\'){
				$result.=$buffer[$index];
				$result.=$buffer[$index+1];
				$index++;
				continue;
			}
			else if($buffer[$index]=='\''){
				
				return $result;
			}
			else{
				$result.=$buffer[$index];
			}
		}
		throw new \Exception('字符串示结束');
	}
	
	protected function getToEnd(&$expr,&$index=0,$count=-1,$end=' '){
	
		if($count===-1){
			$count=ark_strlen($expr);
		}
		$result='';
		for (;$index<$count;$index++){
				
			if($expr[$index]===$end){
				$index--;
				return $result;
			}
			else{
				$result.=$expr[$index];
			}
		}
		return $result;
	}
	
	public function parseParams($expr,$index=0,$count=-1){
		if($count===-1){
			$count=ark_strlen($expr);
		}
		$name='';
		$value='';
		$result=array();
		for (;$index<$count;$index++){
			if(preg_match('/\s/', $expr[$index])){
				continue;
			}
			
			$name=$this->getWord($expr,$index,$count);
			$index++;
			if($expr[$index]!='='){
				throw new \Exception($expr.'错误的参数表达式'.$expr[$index]);
			}
			$index++;
			if($expr[$index]=='\('){
				$last=$this->findPairRight($expr,$index+1, '(', ')',$count);
				if($last===-1){
					throw new \Exception('括号未结束');
				}
				$index++;
				$value=$this->parseExpr($expr,$index,$last);
				$result[$name]=$value;
			}
			else if($expr[$index]=='\''){
				$index++;
				$value='\''. $this->getString($expr,$index,$count) .'\'';
				$result[$name]=$value;
			}
			else {
				$value=$this->getToEnd($expr,$index,$count,' ');
				$value=$this->parseExpr($value);
				$result[$name]=$value;
			}
			
			$name='';
			$value='';
		}
		return $result;
	}
	
	public function parseExpr(&$buffer,&$index=0,$count=-1){
		//$result=$this->getWord($buffer,$index,$count);
		//if(ark_strlen($result)===0){
		//	throw new \Exception('错误的变量名');
		//}
		$result='';
		RESTART:
		if($count===-1){
			$count=ark_strlen($buffer);
		}
		
		for ($i=$index;$i<$count;$i++){
			//die('hhh:'.$buffer[$i]);
			if($buffer[$i]=='.'){
				$i++;
				$result .='->'.$this->getWord($buffer,$i,$count);
			}
			else if($buffer[$i]=='('){
				$last=$this->findPairRight($buffer,$i+1, '(', ')',$count);
				if($last===-1){
					throw new \Exception('括号未结束');
				}
				$index=$i+1;
				
				$result .='('.$this->parseExpr($buffer,$index,$index+$last-$i-1).')';
				$i=$last;
			}
			else if($buffer[$i]=='['){
				$last=$this->findPairRight($buffer,$i+1,$count, '[', ']');
				if($last===-1){
					throw new \Exception('括号未结束');
				}
				
				$index=$i+1;
				
				$result .='['.$this->parseExpr($buffer,$index,$index+$last-$i-1).']';
				$i=$last;
			}
			else if($buffer[$i]=='\''){
				$i++;
				$result .='\''. $this->getString($buffer,$i,$count) .'\'';
			}
			else if($buffer[$i]=='$'){
				$i++;
				$result .='$view->data[\''. $this->getWord($buffer,$i,$count) .'\']';
			}
			else if($buffer[$i]=='#'){
				$index=$i+1;
				$result .='$'. $this->getWord($buffer,$index,$count);
				goto RESTART;
			}
			else if($buffer[$i]=='@'){
				$i++;
				$result .='$view->call['. $this->getWord($buffer,$index,$count) .']';
				goto RESTART;
			}
			else{
				$result.=$buffer[$i];
			}
		}
		
		
		
		
		
		return $result;
	}
	
	protected function processToken($matches){
		if(!$matches || !isset($matches[1])){
			return '';
		}
		
		
		//var_dump($matches);
		//$result='';
		$expr=trim($matches[1]);
		if($this->_literal===TRUE && preg_match('/\/\s*literal/i',$expr)){
			$this->_literal=FALSE;
			return '';
		}
		else if($this->_literal===FALSE && preg_match('/literal/i',$expr)){
			$this->_literal=TRUE;
			return '';
		}
		
		
		if($this->_literal===TRUE){
			return $matches[0];
		}
		
		$filters=array();
		$filters[]=new VarParser($this);
		$filters[]=new IfParser($this);
		$filters[]=new ForParser($this);
		
		foreach ( $filters as $parser ) {
			$result = $parser->parse ( $expr );
			if ($result == Parser::PARSE_CONTINUE) {
				continue;
			} else if ($result == Parser::PARSE_DONE) {
				return $parser->getResult ();
				break;
			} else {
				throw new \Exception ( '解析：' . $result );
			}
		}
		return ' UNKOWN TOKEN( '. $expr .')';
	}
	
	
	
	protected function compileLine($line){
		$this->result.= preg_replace_callback('/\{\s*(.*)\s*\}/i', array($this,'processToken'), $line).chr(13).chr(10);
	}
	public function getBlockCount($token){
		if(!isset($this->_blocks[$token])){
			$this->_blocks[$token]=0;
		}
		return $this->_blocks[$token];
	}
	/**
	 * 打开一个块标签
	 * @param unknown $token
	 */
	public function openBlock($token){
		if(!isset($this->_blocks[$token])){
			$this->_blocks[$token]=0;
		}
		$this->_blocks[$token]=$this->_blocks[$token]+1;
	}
	/**
	 * 关闭一个块标签
	 * @param unknown $token
	 */
	public function closeBlock($token){
		if(!isset($this->_blocks[$token])){
			$this->_blocks[$token]=0;
		}
		$this->_blocks[$token]=$this->_blocks[$token]-1;
	}
	
	public function compileToString(){
		
		//文件编译名称：compiled_id //不包含路径
		//文件编译时间：complied_time
		//模板原名称：orgin_filename //全路径
		//是否是布局文件：is_master
		//内容页地址（指针）：sub_complied_id
		//是否是子页：is_sub
		//父级页地址（指针）：master_compiled_id
		//是否可作为部分页使用：allow_include
		
		
		//$handle = fopen($this->_tplPath, 'r');
		//while (!feof($handle)) {
		//	$buffer = fgets($handle,1024);
		//	$brarray = explode(' ',$buffer);
		//	
		//}
		
		
		
		
		$reader=new StreamReader($this->_tplPath);
		$reader->open();
		
		while (!$reader->endOfStream()){
			$buffer=array();
			if($reader->readLine($buffer)==0){
				$this->_line++;
				continue;
			}
			else {
				$this->_line++;
				$this->compileLine(implode('', $buffer));
			}
		}
		
		return $this->result;
		
		$content=file_get_contents($this->_tplPath);
		
		$content=preg_replace_callback('/\{(.*)\}/i', array($this,'foundToken'), $content);
		
		//save
		
		return $content;
		
	}
	
}


?>