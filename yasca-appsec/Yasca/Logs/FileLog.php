<?
declare(encoding='UTF-8');
namespace Yasca\Logs;
use \Yasca\Core\Iterators;

/**
 * @author Cory Carson <cory.carson@boeing.com> (version 3)
 */
final class FileLog extends \Yasca\Log {
	use \Yasca\Core\Closeable;

	protected function innerClose(){
		unset($this->fileObject);
	}

	const OPTIONS = <<<'EOT'
--log,FileLog[,filename,levels]
filename: The name of the file to write, relative to the current working directory
levels: The numerical value of the level flags (DEBUG: 1, INFO: 2, ERROR: 4)
EOT;

	private $fileObject;
	private $levels;
	public function __construct($args){
		$this->fileObject = new \SplFileObject(
			Iterators::elementAtOrNull($args, 0),
			'w'
		);

		$this->levels = Iterators::elementAtOrNull($args, 1);
		if ($this->levels === null){
			$this->levels = (Level::DEBUG | Level::INFO | Level::ERROR);
		}
	}

	public function update(\SplSubject $subject){
		list($message, $severity) = $subject->value;
		if (($severity & $this->levels) !== $severity){
			return;
		} elseif ($severity === Level::DEBUG){
			$this->fileObject->fwrite("DEBUG  $message\n");
		} elseif ($severity === Level::INFO){
			$this->fileObject->fwrite("INFO   $message\n");
		} elseif ($severity === Level::ERROR){
			$this->fileObject->fwrite("ERROR  $message\n");
		} else {
			//Ignore it.
		}
	}
}