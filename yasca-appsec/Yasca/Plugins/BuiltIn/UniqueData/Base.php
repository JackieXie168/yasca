<?
declare(encoding='UTF-8');
namespace Yasca\Plugins\BuiltIn\UniqueData;
use \Yasca\Core\Iterators;
use \Yasca\Core\Operators;

trait Base {
	use \Yasca\AggregateFileContentsPlugin, \Yasca\Plugins\BuiltIn\Base;

	protected function getSupportedFileClasses(){
		return [
			'JAVA', 'JAVASCRIPT', 'C', 'HTML', 'PHP', 'NET',
			'PYTHON', 'PERL', 'COBOL', 'RUBY', 'TEXT',
		];
	}

	abstract protected function getUniqueData($fileContents);

	private $uniqueData = [];

	public function getResultIterator(){
		if (Iterators::any($this->uniqueData) === true){
			return $this->newResult()->setOptions([
				'unsafeData' => \array_keys($this->uniqueData),
			]);
		} else {
			return new \EmptyIterator();
		}
	}

    public function apply($fileContents){
    	foreach ($this->getUniqueData($fileContents) as $data){
    		$this->uniqueData[$data] = true;
    	}
    }
}