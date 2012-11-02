<?
declare(encoding='UTF-8');
namespace Yasca\Reports;
use \Yasca\Core\Closeable;
use \Yasca\Core\Iterators;
use \Yasca\Core\JSON;
use \Yasca\Core\Operators;

final class HtmlFileReport extends \Yasca\Report {
	use Closeable;

	const OPTIONS = <<<'EOT'
--report,HtmlFileReport[,filename]
filename: The name of the file to write, relative to the current working directory
EOT;

	private $fileObject;
	private $firstResult = true;

	public function __construct($args){
		$this->fileObject =
			(new \Yasca\Core\FunctionPipe)
			->wrap($args)
			->pipe([Iterators::_class, 'elementAt'], 0)
			->pipe([Operators::_class, '_new'], 'w', '\SplFileObject')
			->unwrap();

		$c = static function(callable $c){return $c();};
		$this->fileObject->fwrite(<<<"EOT"
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
<title>Yasca v{$c(static function(){return \Yasca\Scanner::VERSION;})} - Report</title>
<style type="text/css">
{$c(Operators::curry('\file_get_contents', __DIR__ . '/HtmlFileReport.css'))}
</style>
<script type="text/javascript">
{$c(Operators::curry('\file_get_contents', __DIR__ . '/jquery-1.8.2.min.js'))}
</script>
<script type="text/javascript">
{$c(Operators::curry('\file_get_contents', __DIR__ . '/HtmlFileReport.js'))}
</script>
</head>
<body>
<table class="header" cellspacing="0" cellpadding="0">
	<tr>
		<td class="title">Yasca</td>
        <td style="width: 100%;">
        	<table style="border:0;">
            <tr>
            <td class="header_left">Yasca Version:</td>
            <td class="header_right">{$c(static function(){return \Yasca\Scanner::VERSION;})} [ <a target="_blank" href="http://sourceforge.net/projects/yasca/files/">check for updates</a> ]</td>
            </tr>
            <tr><td class="header_left">Report Generated:</td>
            <td class="header_right">
{$c(static function(){return \htmlspecialchars(\date(\DateTime::RFC850),ENT_NOQUOTES);})}
            </td></tr>
            </table>
    	</td>
    	<td class="saveB">
    		<input id="saveJson" type="button" value="Save JSON Report"/>
    	</td>
    </tr>
</table>

<h1 id="loading">Loading result <span id="loadingNum">0</span> of <span id="loadingOf">0</span></h1>
<div id="resultsJson" style="display:none;">[
EOT
	);
}
	public function update(\SplSubject $subject){
		$result = $subject->value;
		if ($this->firstResult === true){
			$this->firstResult = false;
		} else {
			$this->fileObject->fwrite(',');
		}
		(new \Yasca\Core\FunctionPipe)
		->wrap($result)
		->pipe([JSON::_class,'encode'], JSON_UNESCAPED_UNICODE)
		->pipe('\htmlspecialchars', ENT_NOQUOTES)
		->pipe([$this->fileObject,'fwrite']);
	}

	protected function innerClose(){
		$this->fileObject->fwrite(<<<'EOT'
]</div>
</body>
</html>
EOT
		);
		unset($this->fileObject);
	}
}