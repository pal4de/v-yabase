<?php

/*       ..:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::.
    ..:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::''''''''''''''''''''''''''''::::::::::::::::::::
  .::::::'''                                                  '::: /""""""""""""""""""""""""""\ :::::::::::::::::::
.::::''     _,=%%%%%%%=._       #%%%%%%%%%%%%%%%%%%%%%%%%%%\   ::' |  v1.0.0 | FIRST RELEASE  | :::::::::::::::::::
::::   ,=%%=-'        `-=%#=.                          `;%%'  .:: <__________________________/ ::::::::::::::::::::
::::  %%*                 `-%%.                  |   ,%%-'   .:::.............................:::::::::::::::::::::
::::                         `%%._                ,;%%'             '::::::::::::::::::::::::::::::::::::::::::::::
::::  ##             ,#,        `:%%;._      _,;%%:'       /%%%%%%+.  '::::::::::::::::::::::::::::::::::::::::::::
::::  %%           ,%#'             `*=%%%%%%=+'           %%     `%;  ::::::::::::::::::::::::::::::::::::::::::::
::::  %%         ,%#'                                      %%    _;%'                                         '::::
::::  %%       ,%#'                          ,+%%%%+.      %%  #%%%%:.     ,+%%%%+.     .+%%%%%%#    ,+%%%%+.  ::::
::::  %%     ,%#'  #%%%%%%%#  ##       ##  ;%*      `%;    %%       `%;  ;%*      `%;  %(          ;%'     )%  ::::
::::  %%   ,%#'               %%       %%  %%        %%    %%        %%  %%        %%  '+%%%%%%+.  %%%%%%%%+'  ::::
::::  %% ,%#'  .:::::::::::.  +%.     ,%%  *%;_    _,%%    %%       ,%;  *%;_    _,%%          )%  *%.         ::::
::::  +%%%'  .::::::::::::::   `+%%%%"^%%    `+%%%%"^##    \%%%%%%%%+'     `+%%%%"^##  #%%%%%%%+'   `+%%%%%%#  ::::
::::.      .::::::::::::::::           %%  :.                                                                 .::::
::::::::::::::::::::::::::::  %#.     ,%;  ::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
::::::::::::::::::::::::::::   `+%%%%%+'   ::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
:::::::::::::::::::::::::::::.           .:::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
'::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::*/

/**
 * V-yaBase - HTML Processor
 *
 * @author     URNR <3t3rna7681@gmail.com>
 * @copyright  URNR All Rights Reserved
 * @version    1.0.0 2018/02/21
**/

declare(strict_types = 1);

namespace URNR\Vyabase;

const VYA_DIR = __FILE__;
const VYA_CONFIG = [
	'vyabase' => [
		'version' => [1, 0, 0],
		'expose_vyabase' => true,

		'strict_mode' => [ //未実装
			'_' => false,
			'force_naming' => false,
			'shorthand_syntax' => true,
			'template' => [
				'symbol_only' => true
			],
		],

		'include' => [
			'use_doc_root' => false,
		],

		'print' => [
			'indent_string' => "\t",

			'minify' => [
				'_' => false,
				'smart_minify' => true, //未実装
			],

			'vya_comment_to_html' => false,
			'delete_html_comment' => true,
			'delete_empty_line' => true,
			'auto_escape' => true, //未実装
		],

		'encode' => [
			'source' => 'UTF-8', //未実装
			'template' => 'UTF-8', //未実装
			'display' => 'UTF-8' //未実装
		],

		'debug' => [
			'_' => false,
			'show_benchmark' => true, //未実装
			'keep_vya_tag' => false,
			'give_random_name' => false,
		],
	],

	'vyatag' => [
		'default_tag' => 'div',
		'xhtml_mode' => false,
	]
];

/**
 * V-yaBase Class
 *
**/
class Vyabase implements \ArrayAccess, \Serializable, \Countable, \IteratorAggregate
{
	use DebugInfoProcessor;
	use VyaConfig;

	protected $name;
	protected $isChild = false;

	protected $html = [];
	protected $wrapper = ['%c'];

	protected $current;
	protected $root;
	protected $parent;
	protected $children = [];

	public function __construct(string $name = '')
	{
		static $calledCount = 0;
		if (empty($name) && $this->getConfig('debug.give_random_name')) {
			srand(++$calledCount);
			$name = 'unnamed-'.sprintf('%04d', rand(0, 9999));
		} elseif (preg_match('~[^a-z_\\-\\d]~i', $name)) {
			$namePrev = $name;
			$name = preg_replace('~[^a-z_\\-]++~i', '_', $namePrev);
			ee(
				'warn',
				'Vyabase name allows alphabets, numbers, underscore and hyphen ([a-zA-Z0-9_\\-]+).'.
				' The name "'.$namePrev.'" are modified to "'.$name.'"'
			);
		}
		$this->name = $name;

		$this->current = $this;
		$this->root = $this;
		$this->parent = $this;
	}

	/*/ テキスト追加関数 /////////////////////////////////////////*/

		/**
		 * 文章データを代入する
		 *
		 * $textに配列が渡された場合、そのそれぞれについて追加をします。
		 * 多次元配列の場合、二次以降の配列を子として追加します。
		 * 配列のキーを持つ時、そのキーを名前とする新しいVyabaseオブジェクトが作成され、その中に格納されます。
		 * ___
		 *
		 * 以下の記号から始められる文字列は特殊なコマンドとして評価されます。
		 *
		 * - \# : 新しい子Vyabase
		 * - [ : 新しい子Vyabaseを開く (Vyabase::wrapstart()相当)
		 * - ] : 子Vyabaseを閉じる (Vyabase::wrapend()相当)
		 * - = : PHP式として評価
		 *   + phpで有功な式が含めます。
		 * - : : 別ファイルをインクルード
		 *   + 一度phpとして実行された結果が用いられます
		 * - ~ : Emmet式として評価
		 * - % : 現在Vyabaseの特定部分
		 *   + %content、%c :その時点でVyabaseがもつ文章データ
		 *   + %wrapped、%w :%contentに加え、これがwrapperによって加工されたもの
		 *
		 * これらの記号はバックスラッシュ(\\)によりエスケープすることが出来ます。
		 *
		 * @param mixed $text 追加したいデータまたはEmmet式
		 *
		 * @return Vyabase $this
		**/
		public function set($text) :Vyabase
		{
			$hasVyaTag = Vyabase::_set_longhand($text);

			if ($hasVyaTag) {
				$this->html = Vyabase::parse($text, $this);
			} else {
				if (!is_array($text)) $text = [$text];
				$this->html = $text;
			}

			return $this;
		}
		/**
		 * dnShorthand Syntaxを展開する感じで...こう...
		 **/
		private static function _set_longhand(&$text) :bool
		{
			$hasVyatag = false;

			if (is_array($text)) {
				foreach ($text as &$textIndex) {
					$hasVyatagChild = Vyabase::_set_longhand($textIndex);
					if ($hasVyatagChild) $hasVyatag = true;
				}
			} elseif (is_string($text)) {
				if (preg_match(
					'~^'.
					'(?<slash>\\\\?)'.
					'(?<command>'.
						'['.Vyabase::PARSE_METACHARS.'{\\\\]'.
						'.*'.
					')'.
					'$~i',
					$text,
					$matches
				)) {
					if (empty($matches['slash'])) {
						if ($matches['command']{0} === '\\') {
							$text = substr($matches['command'], 1);
						} else {
							$text = '<?vya '.$matches['command'].' ?>';
						}
					} else {
						$text = $matches['command'];
					}
				}

				if (!$hasVyatag && preg_match('~^<\\?vya[^a-z\-]~i', $text)) {
					$hasVyatag = true;
				}
			}

			return $hasVyatag;
		}

		/**
		 * wrapperを設定する
		 *
		 * wrapperとは、getRawData()やprint()などが呼び出された際に、自動的に代入される文章データのことです。
		 * wrapperによって修飾された文章データは実際の文章データには影響を及ぼさず、その結果にのみ反映されます。
		 *
		 * set()により実装されているので、%contentなどの置換体が使用できます。
		 * wrapperデータ内に%contentが存在しないとき、実際の文章データが上書きされる形になりますのでご注意下さい。
		 *
		 * @param mixed $wrapper 追加したいwrapperデータ
		 *
		 * @return Vyabase $this
		**/
		public function setWrapper($wrapper) :Vyabase //バリデーション必要かも
		{
			$this->wrapper = $wrapper;

			return $this;
		}

		/**
		 * ラッパーデータを返す
		 *
		 * @return $wrapper
		**/
		public function getWrapper()
		{
			return $this->wrapper;
		}

		/**
		 * 置換子名に従った文章データを切り出す
		 *
		 * 重複防止のために返されたデータは削除されます。
		 *
		 * @param string $replacerName
		 *
		 * @return array $php
		**/
		public function cut(string $name) :array
		{
			$result = [];
			switch($name) {
				case 'c':
				case 'content':
					$result = $this->html;
					break;

				case 'w':
				case 'wrapped':
					$result = $this->getRawData();
					break;

				case 'y':
				case 'yield':
					break;

				default:
					ee('error', 'Unknown replacer name "'.$name.'"');
					break;
			}
			return $result;
		}

		/**
		 * データを消去する
		 *
		 * $modeには以下の引数が与えられます
		 * - 'content': 本文
		 * - 'wrapper': ラッパー
		 * - 'all': 上記のすべて
		 *
		 * @param string $mode = 'content'
		 *
		 * @return Vyabase $this
		**/
		public function clear(string $mode) :Vyabase
		{
			switch ($mode) {
				case 'c':
				case 'content':
					$this->set([]);
					break;

				case 'w':
				case 'wrapper':
					$this->setWrapper(['%c']);
					break;

				case 'all':
					$this->set([]);
					$this->setWrapper(['%c']);
					break;

				default:
					ee('error', 'Unknown clear() mode "'.$mode.'"');
					break;
			}
			return $this;
		}

		/**
		 * 行を後置する
		 *
		 * $textに配列が渡された場合、そのそれぞれについて追加をします。
		 * 多次元配列の場合、二次以降の配列を子として追加します。
		 * 配列のキーを持つ時、そのキーを名前とする新しいVyabaseオブジェクトが作成され、その中に格納されます。
		 * set()同様のコマンドが使用できます。
		 *
		 * @param mixed $text 追加したい文章またはEmmet式
		 *
		 * @return Vyabase $this
		**/
		public function append($text) :Vyabase
		{
			if (!is_array($text)) $text = [$text];
			$this->set(array_merge(
				['%c'],
				$text
			));
			return $this;
		}

		/**
		 * 行を前置する
		 *
		 * $textに配列が渡された場合、そのそれぞれについて追加をします。
		 * 多次元配列の場合、二次以降の配列を子として追加します。
		 * 配列のキーを持つ時、そのキーを名前とする新しいVyabaseオブジェクトが作成され、その中に格納されます。
		 * set()同様のコマンドが使用できます。
		 *
		 * @param mixed $text 追加したい文章またはEmmet式
		 *
		 * @return Vyabase $this
		**/
		public function prepend($text) :Vyabase
		{
			if (!is_array($text)) $text = [$text];
			$this->set(array_merge(
				$text,
				['%c']
			));
			return $this;
		}

		/**
		 * 現在の文章を包む
		 *
		 * 現在の文章をインデントした上でテキストを前後に追加します。
		 * 第一、二引数に配列を与えることは出来ません。
		 *
		 * @param mixed $top 文章またはEmmet式
		 * @param ?string $bottom 文章 ($topにEmmet式を与える場合にはnull)
		 * @param string $nameForOld 包む以前の文章に与える名前
		 *
		 * @return Vyabase $this
		**/
		public function wrap(string $top, ?string $bottom = null, string $nameForOld = '') :Vyabase
		{
			if ($top{0} === '~' && $bottom === null) {
				$top = new VyaTag(substr($top, 1), 'auto');
				$bottom = $top;
			}
			return $this->set([
				$top,
				$nameForOld => ['%c'],
				$bottom
			]);
		}

		/**
		 * 指定IDをもつVyabaseに行をsetする
		 *
		 * $name => $text のように、添字にIDを、値にテキストを入れて下さい。
		 *
		 * 存在しないIDについては無視されます。
		 *
		 * @param array $textList 割り当てる文字列のリスト
		 *
		 * @return Vyabase $this
		**/
		public function assign(array $textList) :Vyabase
		{
			foreach($textList as $id => $text) {
				if ($this->hasChild($id)) $this->$id->set($text);
			}
			return $this;
		}

		/*/ テキストラップ /*/

		/**
		 * 新しく子Vyabaseを生成、wrapperにテキストを追加し、currentを移す
		 *
		 * 存在しないIDについては無視されます。
		 *
		 * @param string $textList 割り当てる文字列のリスト
		 * @param string $name 名前を与えた場合childとしてアクセスできるようになります
		 *
		 * @return Vyabase $this
		**/
		public function wrapstart(string $text = '', string $name = '') :Vyabase
		{
			$child = new VyabaseChild($this, $name);

			if (isset($text{0}) && $text{0} === '~') {
				$text = substr($text, 1);
				$text = new VyaTag($text, 'opening');
			}
			$text = [
				$text,
				['%c']
			];
			$child->setWrapper($text);
			$child->stay();

			$this->html[] = $child;
			return $child;
		}

		/**
		 * テキストを現在のwrapperに追加し、currentをparentに移す
		 *
		 * 存在しないIDについては無視されます。
		 *
		 * @param string $textList 割り当てる文字列のリスト
		 *
		 * @return Vyabase $this
		**/
		public function wrapend(string $text) :Vyabase
		{
			if ($text{0} === '~') {
				$text = substr($text, 1);
				$text = new VyaTag($text, 'closing');
			}
			$this->setWrapper(array_merge(
				$this->getWrapper(),
				[$text]
			));
			return $this->parent->stay();
		}

		/*/ テキストパース /*/

		/**
		 * 文字列、配列をパースする
		 *
		 * @param array $data
		 * @param Vyabase $parent = null
		 *
		 * @return array $parsed
		**/
		public static function parse($data, Vyabase $parent) :array
		{
			$is_array = true;
			if (is_string($data)) {
				if ($data === '') return [];
				if ($parent->getConfig('print.vya_comment_to_html')) {
					$data = preg_replace('~<(\\??)#(.+?)#\\1>~s', '<!-- $2 -->', $data); //コメント削除
				} else {
					$data = preg_replace('~<(\\??)#(.+?)#\\1>~s', '', $data); //コメント削除
				}

				preg_match_all('~<(\\??)&(.+?)&\\1>~s', $data, $escapeList); //エスケープ
				foreach($escapeList[0] ?? [] as $escapeKey => $escape) {
					$data = str_replace($escape, htmlspecialchars(trim($escapeList[2][$escapeKey])), $data);
				}

				$data = indent2array($data);
			} elseif (is_array($data)) {
			} else {
				if ($data instanceof VyabaseGenerator) $data = $data->getVyabase();

				return [$data];
			}

			return Vyabase::_parser($data, $parent);
		}
		private static function _parser(array $lineList, Vyabase $parentGiven, int $localLevel = 0) :array
		{
			static $isFirstTimeStatic = true; //初回検知用
			$isFirstTime = false; //初回検知用

			static $parsingVyaTag;
			static $vyatagContentStock;
			static $parentHistory;
			static $resultReceiverHistory;

			static $yieldLevel = 0;
			static $yieldQue = [
				'level' => -1,
				'content' => []
			];

			//初期化
			if ($isFirstTimeStatic) {
				$isFirstTime = true;
				$isFirstTimeStatic = false;

				$parsingVyaTag = false;
				$vyatagContentStock = '';

				$parentHistory = [];
				$resultReceiverHistory = [];
			}

			$parent = $parentGiven;

			$result = [];
			$resultReceiver = &$result;

			foreach ($lineList as $lineName => $line) {
				if (is_string($lineName) && $lineName !== '') { //名前がある時は子に渡す
					$resultReceiver[] = (new VyabaseChild($parent, $lineName))->set($line);
					continue;
				}

				if (!is_string($line)) {
					if (is_array($line)) {
						$resultReceiver[] = self::_parser($line, $parent, $localLevel + 1);
					} else {
						if ($line instanceof VyabaseGenerator) $line = $line->getVyabase();
						$resultReceiver[] = $line;
					}
					continue;
				} elseif (strpos($line, "\n")) {
					$resultReceiver = array_merge(
						$resultReceiver,
						self::_parser(indent2array($line), $parent, $localLevel + 1)
					);
					continue;
				}

				preg_match_all(
					'~'.
						'<\\?vya(?![a-z\\-])(?:'.
							'.*?(?<!\\\)\\?>'.
							'|'.
							'.*(?!\\?>)$'.
						')'.
						($parsingVyaTag ? '|^.*?(?:(?<!\\\)\\?>|$)' : '').
					'~i',
					$line,
					$vyatagList
				);

				$vyatagList = $vyatagList[0];

				// vyatagとそうでない部分とで分ける
				$lineXpld = [];
				foreach ($vyatagList as $vyatag) {
					$lineXpldByVyatag = explode($vyatag, $line, 2);
					$line = $lineXpldByVyatag[1];

					if ($lineXpldByVyatag[0] !== '') $lineXpld[] = $lineXpldByVyatag[0];
					$lineXpld[] = $vyatag;
				}
				if (!empty($line)) $lineXpld[] = $line;

				$vyatagList = array_uintersect_uassoc(
					$lineXpld,
					$vyatagList,
					function ($vala, $valb) {
						return $vala === $valb ? 0 : 1;
					},
					function ($keya, $keyb) {
						return 0;
					}
				);

				$lineXpldParsedOrig = [];
				$lineXpldParsed = &$lineXpldParsedOrig;
				$vyalineGen = true;
				$childEnd = false;

				$noReturn = false;

				foreach ($lineXpld as $lineXpldIndexKey => $lineXpldIndex) {
					$lxi = $lineXpldIndex;

					if (!isset($vyatagList[$lineXpldIndexKey])) {
						$lineXpldParsed[] = $lxi;
						continue;
					}

					//vyatagキャプチャリングのフラグを立てる
					$parsingVyaTag = true;

					$lineXpldParsed[] = str_replace('<?vya', '<?\\vya', $lxi);

					$vyatagContentStock .= $lxi;

					//vyatagの処理開始
					if (preg_match('~(?<!\\\\)\\?>~', $lxi)) {
						preg_match(
							'~^'.
							'<\\?vya\\s*+'.
							'(?|'.
								'(?|'.
									'(?<mode>['.Vyabase::PARSE_METACHARS.'])'.
									'|'.
									'\\{(?<mode>[a-z\-]+)\\}'.
								')?\\s*+'.
								'(?<arg>.*)\\s*+'.
								'|'.
								'(?<mode>)'.
								'(?<arg>[a-z_\\-]+)'.
							')?'.
							'\\s*+\\?>'.
							'$~i',
							trim($vyatagContentStock),
							$vyatagContent
						);
						$vtMode = strtolower($vyatagContent['mode']) ?? '';
						$vtArg = trim($vyatagContent['arg'] ?? '');

						//エスケープ解除
						$vtArg = preg_replace('~(?<!\\\\)\\?>~', '?>', $vtArg);
						$vtArg = str_replace('\\\\?>', '\\?>', $vtArg);

						$parsingVyaTag = false;
						$vyatagContentStock = '';
						/*
						 * @ / $ " ' ` ; - _ , を使うのは避けようね！！！
						 * 残り使用可能文字: & ^ | * + . ; -
						 */
						$vtMode = [
							''   => 'child',
							'#'  => 'child',
							'['  => 'child-start',
							']'  => 'child-end',

							'='  => 'php',
							':'  => 'include',
							'~'  => 'emmet',
							'%'  => 'yield',

							// '?'  => 'if',
							// '!'  => 'for',
						][$vtMode] ?? $vtMode;

						switch ($vtMode) {
							case 'child':
							case 'child-start':
								$newChild = new VyabaseChild($parent, str_replace(["\r", "\n"], '', $vtArg));

								switch ($vtMode) {
									case 'child-start':
										$resultReceiver[] = $newChild;

										$parentHistory[] = $parent;
										$parent = $newChild;

										$resultReceiverHistory[] = $resultReceiver;
										$resultReceiver = $newChild;
										continue 3;

									case 'child':
										$vyalineGen = false;
										$lineXpldParsed[] = $newChild;
										break;
								}
								break;

							case 'child-end':

								$childEnd = true;
								continue 2;

							case 'escape':
								$lineXpldParsed[] = $vtArg;
								break;

							default:
								if ($vtArg === '') {
									ee('error', 'Value of the tag can\'t be empty'); //エラーが発生したファイルと行も入れるべき
									continue 2;
								}
								switch ($vtMode) {
									case 'php':
										$called = Vyabase::php($vtArg);
										$lineXpldParsed = array_merge(
											$lineXpldParsed,
											$called
										);
										break;

									case 'include':
										$vyalineGen = false;

										++$yieldLevel;
										$include = Vyabase::include($vtArg, $parent);
										--$yieldLevel;

										if ($yieldQue['level'] !== $yieldLevel) {
											$lineXpldParsed = array_merge(
												$lineXpldParsed,
												$include
											);
										}

										break;

									case 'emmet':
										$vyalineGen = false;
										$lineXpldParsed = array_merge(
											$lineXpldParsed,
											Vyabase::emmet($vtArg, $parent)
										);
										break;

									case 'yield':
										$vyalineGen = false;
										$vtArgXpld = preg_split('~\s++~', $vtArg, 2);
										$vtArg = $vtArgXpld[0];

										//vyatag削除
										switch ($vtArgXpld[0]) {
											case 'c':
											case 'content':
											case 'w':
											case 'wrapped':
												array_pop($lineXpldParsed);
												$lineXpldParsed = array_merge(
													$lineXpldParsed,
													$parent->cut($vtArgXpld[0])
												);
												break;

											case 'y':
											case 'yield':
												$yieldLevelWant = $yieldLevel - (int) ($vtArgXpld[1] ?? 1);
												// if ($yieldLevelWant < 0) $yieldLevelWant = 0;
												$yieldQue = [
													'level' => $yieldLevelWant,
													'content' => &$result
												];
												$lineXpldParsed[] = '<?vya %%y ?>';
												break;
										}
										break;

									default:
										ee('error', 'Unknown oparation "'.$vtMode.'"');
										break;
								}
								break;
						}
					}
				}

				if ($vyalineGen && count($lineXpldParsed) > 1) {
					$vyabaseLine = new VyabaseLine($parent);
					$vyabaseLine->html = $lineXpldParsed;
					$resultReceiver[] = $vyabaseLine;
				} else {
					$resultReceiver = array_merge(
						$resultReceiver instanceof Vyabase ? $resultReceiver->html : $resultReceiver,
						$lineXpldParsed
					);
				}

				if ($childEnd) {
					if (empty($parentHistory)) {
						$parent = $parentGiven;
						$resultReceiver = &$result;
					} else {
						$resultReceiverPrev = $resultReceiver;

						$parent = array_pop($parentHistory);
						$resultReceiver = array_pop($resultReceiverHistory);

						$resultReceiver = array_merge(
							$resultReceiver,
							$resultReceiverPrev
						);
					}
				}
			}

			if ($localLevel === 0 && $yieldQue['level'] === $yieldLevel) {
				array_replace_recursive_once('<?vya %%y ?>', $result, $yieldQue['content']);
				$result = $yieldQue['content'];

				$yieldQue = [
					'level' => -1,
					'content' => []
				];
			} elseif ($localLevel === 0 && $yieldQue['level'] !== -1) {
				$yieldQue['content'] = $result;
			}

			if ($isFirstTime) {
				$isFirstTimeStatic = true;
			}

			return $result;
		}
		private const PARSE_METACHARS = '#[\]=:\\~%';

		/**
		 * PHP式として評価したものを返す
		 *
		 * @param string $code
		 *
		 * @return array $php
		**/
		public static function php(string $code) :array
		{
			$value = eval(
				($code{0} === '$' ? 'global '.$code.';' : '').
				'return '.$code.';'
			);

			if ($value instanceof VyabaseGenerator) $value = $value->getVyabase();

			return [$value];
		}

		/**
		 * Emmet式として評価したものを返す
		 *
		 * $refundsを与えた場合、 _...+name|div>..._ の構文で表現された部分をVyaTagオブジェクトとして、 _name => div_ の形式で返します。
		 *
		 * またの名を **_Power of Darkness_**
		 *
		 * @param string $emmet
		 * @param array &$refunds = null
		 *
		 * @return array $parsed
		**/
		public static function emmet(string $emmet, Vyabase $parent, array &$refunds = null) :array
		{
			$emmet = preg_replace('~\\)[>^]~', ')+', $emmet); //不適切なかっこの後の記号をに+を置換

			if ( //{}[]内以外に半角スペースがあったとき削除
				preg_match(
					'~\\s~',
					preg_replace(
						'~\\[.*?(?<!\\\\)\\]|\\{.*?(?<!\\\\)\\}~',
						'',
						$emmet
					)
				) !== false
			) {
				$emmetExploded = preg_split('~\\[.*?(?<!\\\\)\\]|\\{.*?(?<!\\\\)\\}~', $emmet);
				foreach ($emmetExploded as $emmetExplodedIndex) {
					if (preg_match('~\\s~', $emmetExplodedIndex) !== false) {
						$emmet = str_replace(
							$emmetExplodedIndex,
							str_replace(
								' ',
								'',
								$emmetExplodedIndex
							),
							$emmet
						);
					}
				}
			}

			Vyabase::$_emmet_que = [
				'level' => 0,
				'content' => ''
			];
			Vyabase::$_emmet_refunds = [];
			Vyabase::$_emmet_refunds;

			$result = Vyabase::_emmet($emmet, $parent);

			$refunds = Vyabase::$_emmet_refunds;

			return $result;
		}
		private static $_emmet_que;
		private static $_emmet_refunds;
		private static function _emmet(string $emmet, Vyabase $parent, $level = 1, $time = 1) {
			$result = [];

			$que = &Vyabase::$_emmet_que;
			$refunds = &Vyabase::$_emmet_refunds;

			if (
				preg_match( //丸括弧で囲まれていればなんでもアリ
					'~^'.
					'(?<current>(?<kakko>\\((?:[^()]*?|(?&kakko))+\\)))'.
					'(?<multi>\\*\\d*)*'.
					'(?>'. //あっこうかぁ！
						'(?<relation>\\+?)'. //+のみOK
						'(?<next>.+)'.
					')?'.
					'$~',
					$emmet,
					$emmetMatches
				)
			) {
				$isGrouped = true;

				$current = $emmetMatches['current'] ?? '()';
				$current = substr($current, 1, -1);
			} else {
				preg_match(
					'~^'.
					'(?<current>'.
						'(?:'.
							'[^'.
								'>+^'.
								'{['.
								'*'.
							']++'.
							'|'.
							'(?:\\{.*?(?<!\\\\)})++'.
							'|'.
							'(?:\\[.*?(?<!\\\\)])++'.
							'|'.
							'(?<multi>\\*\\d*+)++'.
						')++'.
					')'.
					'(?>'.
						'(?<relation>[>+^]|(?=\\())'.
						'(?<next>.+)'.
					')?'.
					'$~',
					$emmet,
					$emmetMatches
				);

				$isGrouped = false;

				$current = $emmetMatches['current'] ?? '';
			}

			//refunds用の名前
			$name = $emmetMatches['name'] ?? '';
			$name = substr($name, 0, -1) ?: '';

			//括弧部分
			//エスケープが特殊なのでここだけ切り離す
			if (!$isGrouped) {
				preg_match('~\{.*?(?<!\\\\)}|\[.*?(?<!\\\\)]~', $current, $brackets);
				$current = str_replace($brackets, '', $current);
				$brackets = implode('', $brackets);
			}

			//繰り返し
			$multi = ($emmetMatches['multi'] ?? '*1') ?: '*1';
			$multi = (int) ltrim($multi, '*');
			if (!$isGrouped) $current = preg_replace('~\\*\\d++~', '', $current);

			//次の要素との関係性
			$relation = ($emmetMatches['relation'] ?? '') ?: '+';

			//次の要素
			$next = $emmetMatches['next'] ?? '';

			//がちゃこ～ん
			if (!$isGrouped) $current = $current.$brackets;

			$count = 0;
			while (++$count <= $multi) {
				if ($isGrouped) {
					$result = array_merge(
						$result,
						Vyabase::_emmet($current, $parent, 1, $count)
					);
				} else {
					$currentRplc = $current;
					if ($multi <= 1 && $time > 1) $count = $time;

					//$の処理
					if (strpos($current, '$') !== false) {
						preg_match_all(
							'~(?<dollars>(?:(?<!\\\\)\\$)+)(?:@(?<option>-?\\d*+))?~',
							$currentRplc,
							$matchedNumberingChar
						);
						$matchedList = $matchedNumberingChar[0];
						$dollarsList = $matchedNumberingChar['dollars'];
						$numberingOptionList = $matchedNumberingChar['option'];

						$countRplc = $count;

						foreach ($dollarsList as $index => $dollars) {
							$numberingLength = strlen($dollars);
							$numberingOption = $numberingOptionList[$index];
							$numberingMatch = preg_quote($matchedList[$index]);

							if ($numberingOption !== '') { //オプション付きのとき
								if ($numberingOption{0} === '-') {
									$numberingOption = substr($numberingOption, 1);
									$numberingOption = $numberingOption !== '' ? (int) $numberingOption : 1;
									$countRplc = $numberingOption + ($multi - $countRplc);
								} else {
									$numberingOption = (int) $numberingOption;
									$countRplc = $numberingOption + $countRplc - 1;
								}
							}

							$currentRplc = preg_replace(
								'~(?<!\\\)'.$numberingMatch.'~',
								sprintf('%0'.$numberingLength.'d', $countRplc),
								$currentRplc,
								1
							);
						}

						$currentRplc = str_replace('\\$', '$', $currentRplc);
					}

					$vyatag = new VyaTag($currentRplc, $relation === '>' ? 'auto' : 'single');
					if ($vyatag->getName() === '?vya') {
						$vyatag = Vyabase::parse((string) $vyatag, $parent);
					} elseif ($vyatag->getName() === '?php') {
						$vyatag = Vyabase::php($vyatag->getText());
					} else {
						$vyatag = [$vyatag];
					}
					$result = array_merge(
						$result,
						$vyatag
					);
				}

				if ($next !== '') {
					switch ($relation) { //nextの処理
						case '>':
							$result[] = Vyabase::_emmet($next, $parent, $level + 1, $count);
							if (isset($vyatag)) {
								$result = $result = array_merge($result, $vyatag);
							}
							break;

						case '+':
							if ($count === $multi) {
								$result = array_merge(
									$result,
									Vyabase::_emmet($next, $parent, $level, $count)
								);
							}
							break;

						case '^':
							if ($count === $multi) {
								preg_match('~^\\^*~', $next, $hats);
								$que['level'] = $level - strlen($hats[0]) - 1;
								if ($que['level'] < 1) $que['level'] = 1;
								$que['content'] = preg_replace('~^\\^*~', '', $next);
							}
							break;

						case '':
							// nothing to do
							break;

							default:
							ee('error', 'Invalid relation command "'.$relation.'"'); //呼ばれることは無さそう
							return [];
							break;
					}
				}
			}

			while ($que['level'] === $level && $que['level'] > 0) {
				$queContent = $que['content'];

				$que = [
					'level' => 0,
					'content' => ''
				];

				$result = array_merge(
					$result,
					Vyabase::_emmet($queContent, $parent, $level)
				);
			}

			if ($name !== '') {
				if ($isGrouped) {
					$refunds[$name] = (new Vyabase($name))->set($result);
				} else {
					$refunds[$name] = $vyatag;
				}
			}

			return $result;
		}

		/**
		 * 指定ファイルをPHPとして実行した後、パースする
		 *
		 * @param string $path
		 * @param Vyabase $parent
		 *
		 * @return array $parsec
		**/
		public static function include(string $path, Vyabase $parent) :array
		{
			static $cwd = null;

			$pathOrig = $path;
			$path = str_replace('\\', '/', $path);

			$isFirstTime = false;
			if ($cwd === null) {
				//vyabase.php以外の最後に呼び出されたファイルのdirname
				$backtrace = debug_backtrace();
				$backtraceFileList = array_column($backtrace, 'file');
				foreach($backtraceFileList as $backtraceFile) {
					if (
						strrpos($backtraceFile, 'vyabase.php') !==
							(strlen($backtraceFile) - strlen('vyabase.php'))
					) {
						break;
					}
				}
				$cwd = dirname($backtraceFile);

				$isFirstTime = true;
			}

			//ルートパス判別
			if (DIRECTORY_SEPARATOR === '\\') { //windowsのとき
				$isAbsPath = (bool) preg_match('~^(?:[a-z]:)?/~i', $path, $root);
				$root = $root[0] ?? 'c:/'; //configで設定できるようにした方がいいのでは？
				$path = str_replace($root, '/', $path);
			} else { //それ以外
				$isAbsPath = ($path{0} ?? '') === '/';
				$root = '/';
			}

			if ($isAbsPath) {
				if ($parent->getConfig('include.use_doc_root')) {
					$path = $_SERVER['DOCUMENT_ROOT'].'/'.$path;
				} else {
					$path = $root.$path;
				}
			} else {
				$path = $cwd.'/'.$path;
			}

			$path = realpath($path);
			$cwd = dirname($path ?: '');

			if ($path) {
				$path = str_replace('\\', '/', $path);
				$extention = explode(
					'.',
					substr($path, strrpos($path, '/') + 1),
					2
				)[1] ?? '';
				switch ($extention) {
					case 'vya.php':
					case 'php': //パースしないほうがいいのでは？？
						$content = execute($path);
						break;

					case 'vya':
						$content = file_get_contents($path);

						if (strpos($content, '//') !== false) {
							$content = preg_replace('~\\s*(?>//[^\\]}]*?$)~m', '', $content);

							if (strpos($content, '//') !== false) {
								$content = preg_replace_callback(
									'~^.*//.*+$~m',
									function ($match) {
										$match = $match[0];
										preg_match_all('~\[.*?(?<!\\\\)]|\{.*?(?<!\\\\)}~', $match, $brackets);
										$brackets = $brackets[0];
										$match = str_replace($brackets, '', $match);
										$match = preg_replace('~//.*$~m', '', $match);
										return $match.implode('', $brackets);
									},
									$content
								);
							}
						}

						$content = '<?vya ~'.array2emmet(indent2array($content)).'?>';
						break;

					case 'vya.html':
					case 'vya.xml':
					default:
						$content = file_get_contents($path);
						break;
				}

				$return = Vyabase::parse($content, $parent);
			} else {
				ee('error', 'Vya template file "'.$pathOrig.'" wasn\'t found'); //エラーが発生したファイルと行も入れるべき
				$return = [];
			}

			if ($isFirstTime) {
				$cwd = null;
			}

			return $return;
		}

	/*/ 階層関係関連 ////////////////////////////////////////////*/

		/**
		 * 現在のVyabaseオブジェクトをcurrentとして登録する
		 *
		 * currentとして登録されたオブジェクトはその属するVyabase群のどこからでもcurrent()からアクセスできるようになります。
		 *
		 * @return Vyabase $this
		**/
		public function stay() :Vyabase
		{
			$this->root->current = $this;
			return $this;
		}

		/**
		 * rootを返す
		 *
		 * @return Vyabase $root
		**/
		public function &root() :Vyabase
		{
			return $this->root;
		}
		/**
		 * parentを返す
		 *
		 * @return Vyabase $parent
		**/
		public function &parent() :Vyabase
		{
			return $this->parent;
		}
		/**
		 * currentを返す
		 *
		 * @return Vyabase $current
		**/
		public function &current() :Vyabase
		{
			return $this->root->current;
		}
		/**
		 * 自分自身を返す
		 *
		 * @return Vyabase $self
		**/
		public function &self() :Vyabase
		{
			return $this;
		}
		/**
		 * 指定名の子を返す
		 *
		 * @param string $name
		 *
		 * @return Vyabase $child
		**/
		public function &child(string $name) :Vyabase
		{
			$children = $this->root->children;
			if (isset($children[$name])) {
				return $children[$name];
			} else {
				ee('error', 'Given child name "'.$name.'" wasn\'t found');
				return $this;
			}
		}

		/**
		 * 与えられた文字列により様々なVyabaseオブジェクトを返す
		 *
		 * @param string $dest
		 *
		 * @return Vyabase $navigate
		**/
		private function &navigate(string $dest) :Vyabase
		{
			switch ($dest) {
				case 'root':
				case 'parent':
				case 'current':
				case 'self':
					return $this->$dest();
					break;
				default:
					return $this->child($dest);
					break;
			}
		}

		/**
		 * 子を持つか調べる
		 *
		 * $nameが与えられた場合はその子が存在するか調べます。
		 * $nameが与えられない場合はこのVyabaseオブジェクトが子を持つか調べます。
		 *
		 * @param Vyabase $name
		 *
		 * @return bool $hasChild
		**/
		public function hasChild(string $name = '') :bool
		{
			if ($name === '') {
				return $this->root->children !== [];
			} else {
				return isset($this->root->children[$name]);
			}
		}

		/**
		 * 子になる
		**/
		protected function beChild(Vyabase $newParent) :Vyabase
		{
			unset($this->current);
			$this->isChild = true;

			$this->parent = $newParent;
			$newParent->addChild($this->name, $this);

			return $this;
		}

		/**
		 * 子が増える
		**/
		protected function addChild(string $name, Vyabase $child) :Vyabase
		{
			if ($name !== '') {
				if ($this->hasChild($name)) {
					ee(
						'warn',
						'Child name conflicting detected ("'.$name.'")! The older child has been overwritten'
					);
				}
				$this->children[$name] = $child;
			}

			return $this;
		}

	/*/ その他 /////////////////////////////////////////////////*/

		/**
		 * 文章データを持つか調べる
		 *
		 * @return bool $hasContent
		**/
		public function hasContent() :bool
		{
			return !empty($this->html);
		}

		/**
		 * wrapperにより加工した文章データを返す
		 *
		 * 命名に難があるのはわかっている
		 *
		 * @return array $rawData
		**/
		public function getRawData() :array
		{
			if ($this->getWrapper() === ['%c'] || $this->getWrapper() === ['%content']) {
				return $this->html;
			}

			$clone = clone $this;

			$clone->set($clone->getWrapper());

			return $clone->html;
		}

		/**
		 * このVyabaseオブジェクトの名前を返す
		 *
		 * @return string $name
		**/
		public function getName() :string
		{
			return $this->name;
		}

	/*/ 出力 ///////////////////////////////////////////////////*/

		/**
		 * Vyabaseオブジェクトを文字列として出力する
		 *
		 * @return string $printed
		**/
		public function print() :string
		{
			$result = trim(
				static::_printer($this->getRawData()),
				"\r\n"
			);

			if ($this->getConfig('print.delete_html_comment')){
				preg_replace('~<!--.*-->~', '', $result);
			}

			if ($this->getConfig('debug.keep_vya_tag')) {
				$result = str_replace('<?\\vya', '<?vya', $result);
			} else {
				$result = preg_replace('~\\s*<\\?\\\\vya.*?(?<!\\\\)\\?>~', '', $result);
			}

			return trim($result, "\r\n");
		}
		private function _printer(
			$html,
			int $indentLevel = 0,
			bool $min = false,
			bool $indent = false
		) :string
		{
			$minify = $this->getConfig('print.minify');
			$indentString = $this->getConfig('print.indent_string');
			$deleteEmptyLine = $this->getConfig('print.delete_empty_line');

			if ($html instanceof VyabaseGenerator) $html = $html->getVyabase();
			if ($html instanceof Vyabase) {
				$indent = false;

				if ($html->getConfig('print.minify')) {
					$html = static::_printer(
						$html->getRawData(),
						$indentLevel,
						true
					);
				} else {
					$html = $html->getRawData();
				}
			}

			if (is_array($html)) {
				$result = '';
				if ($indent) ++$indentLevel;
				foreach ($html as $htmlIndex) {
					$result .= static::_printer(
						$htmlIndex,
						$indentLevel,
						$min,
						true
					);
				}
				if ($indent) --$indentLevel;
				return $result;
			} else {
				$html = (string) $html;

				if ($html !== '' || !$deleteEmptyLine) {
					if ($minify || $min) {
						return $html;
					} else {
						return str_repeat($indentString, $indentLevel).$html.PHP_EOL;
					}
				} else {
					return '';
				}
			}
		}

	/*/ マジックメソッド ///////////////////////////////////////////*/

		public function __toString()
		{
			return $this->print();
		}

		public function __clone()
		{
			$this->name = null;
		}

		public function &__invoke(string $dest = 'current') :Vyabase
		{
			return $this->navigate($dest);
		}

		public function &__get(string $dest)
		{
			return $this->navigate($dest);
		}

		public function __debugInfo()
		{
			return $this->debugInfoProcessor([
				'name',
				[
					'name' => 'localConfig',
					'display' => 'config',
				],
				[
					'name' => 'root',
					'if' => function ($prop) {
						return $prop !== $this && $prop->name !== '';
					},
					'return' => function ($prop) {
						return $prop->name;
					},
				],
				[
					'name' => 'parent',
					'if' => function ($prop) {
						return $prop !== $this && $prop->name !== '';
					},
					'return' => function ($prop) {
						return $prop->name;
					},
				],
				[
					'name' => 'children',
					'return' => function ($prop) {
						return array_keys($prop);
					},
				],
				[
					'name' => 'wrapper',
					'if' => function ($prop) {
						return !($prop === ['%c']);
					},
				],
				'html',
			]);
		}

	/*/ ArrayAccess //////////////////////////////////////////////*/

		public function offsetExists($offset) :bool
		{
			if (is_string($offset)) {
				return isset($this->children[$offset]);
			} else {
				return isset($this->html[$offset]);
			}
		}

		public function &offsetGet($offset)
		{
			if ($offset === null || $offset === '') $offset = 'current';

			if (is_string($offset)) {
				return $this->navigate($offset);
			} else {
				$offset = (int) $offset;
				if ($offset < 0) {
					$offset = count($this->html) + $offset; + 1;
				}
				return $this->html[$offset];
			}
		}

		public function offsetSet($offset, $value) :void
		{
			if (empty($offset)) {
				$this->append($value);
			} else {
				if (is_string($offset)) {
					$child = &$this->navigate($offset);
					$child->set($value);
				} else {
					$offset = (int) $offset;
					$this->html = array_merge(
						array_slice($this->html, 0, $offset - 1),
						Vyabase::parse($value, $this),
						array_slice($this->html, $offset + 1)
					);
				}
			}
		}

		public function offsetUnset($offset) :void
		{
			unset($this->html[$offset]);
			$this->html = array_values($this->html);
		}

	/*/ Counable /////////////////////////////////////////////////*/

		public function count() :int
		{
			return count($this->html);
		}

	/*/ IteratorAggregate ////////////////////////////////////////*/

		public function &getIterator()
		{
			foreach ($this->html as &$line) {
				yield $line;
			}
		}

	/*/ Serializable /////////////////////////////////////////////*/

		public function serialize() :array
		{
			return [];
		}

		public function unserialize($serialized) :void
		{
		}
}

/**
 * Vyabaseの子として自動生成される
 */
class VyabaseChild extends Vyabase
{
	public function __construct(Vyabase $parent, string $name = '')
	{
		parent::__construct($name);
		$this->beChild($parent);
		$this->root = $this->parent->root;
	}
}

/**
 * 自動でminifyなchild
 * 命名不可
 */
class VyabaseLine extends VyabaseChild
{
	public function __construct(Vyabase $parent)
	{
		parent::__construct($parent, '');
		$this->setConfig('print.minify', true);
	}
}

/**
 * setを検知次第中身を親に渡す
 */
class VyabaseTemp extends Vyabase
{
	protected $percents = [];
}

/*||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||*\
|*|
|*|  Html Source Section
|*|
\*||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||*/

interface VyabaseGenerator
{
	public function getVyabase() :Vyabase;
}

/*||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||*\
|*|
|*|  VyaTag
|*|
\*||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||*/

class VyaTag {
	use DebugInfoProcessor;
	use VyaConfig;

	private $name = '';
	private $id = '';
	private $class = [];
	private $style = [];
	private $attr = [];
	private $text = '';

	private const TYPE_NORMAL = 0;
	private const TYPE_EMPTY = 1;
	private const TYPE_PI = 2;
	private const TYPE_DECLARATION = 3;

	private $tagType;

	private $defaultMode; //auto|single|opening|closing|text
	private $countPrinted = 0;

	protected static $abbrList = [
		'src' => 'source',

		'opt' => 'option',

		'btn' => 'button',

		'fset' => 'fieldset',
		'fst' => 'fieldset',

		'bq' => 'blockquote',

		'fig' => 'figure',
		'figc' => 'figurecaption',

		'pic' => 'picture',

		'optg' => 'optgroup',

		'leg' => 'legend',

		'sect' => 'section',

		'art' => 'article',

		'hdr' => 'header',

		'ftr' => 'footer',

		'adr' => 'address',

		'dlg' => 'dialog',

		'str' => 'strong',

		'prog' => 'progress',

		'mn' => 'main',

		'tem' => 'template',

		'datal' => 'datalist',

		'out' => 'output',

		'det' => 'details',

		'textarea' => '[cols="30" rows="10"]',

		'iframe' => '[frameborder="0"]',

		'tarea' => '[cols="30" rows="10"]',
	];

	protected static $optnList = [
		'bdo' => [
			'r' => '[dir="rtl"]',
			'l' => '[dir="ltr"]',
		],

		'link' => [
			'css' => '[rel="stylesheet" href="style.css"]',
			'print' => '[rel="stylesheet" href="print.css" media="print"]',
			'favicon' => '[rel="shortcut icon" rel="shortcut icon" href="favicon.ico" type="image/x-icon"]',
			'touch' => '[rel="apple-touch-icon" href="favicon.png"]',
			'rss' => '[rel="alternate" href="rss.xml" type="application/rss+xml" title="RSS"]',
			'atom' => '[rel="alternate" href="atom.xml" type="application/atom+xml" title="Atom"]',
			'import' => '[linkrel="import" href="component.html"]',
			'im' => '[linkrel="import" href="component.html"]',
		],

		'meta' => [
			'utf' => '[http-equiv="Content-Type" content="text/html;charset=UTF-8"]',
			'win' => '[http-equiv="Content-Type" content="text/html;charset=windows-1251"]',
			'vp' => '[name="viewport" content="width=device-width, initial-scale=1.0"]',
			'compat' => '[http-equiv="X-UA-Compatible" content="IE=7"]',
		],

		'area' => [
			'd' => '[shape="default"]',
			'c' => '[shape="circle]',
			'r' => '[shape="rect"]',
			'p' => '[shape="poly"]',
		],

		'form' => [
			'get' => '[method="get"]',
			'post' => '[method="post"]'
		],

		'input' => [
			'hidden' => '[type="hidden"]',
			'text' => '[type="text"]',
			'search' => '[type="search"]',
			'email' => '[type="email"]',
			'text' => '[type="text"]',
			'url' => '[type="url"]',
			'password' => '[type="password"]',
			'datetime' => '[type="datetime"]',
			'date' => '[type="date"]',
			'datetime-local' => '[type="datetime-local"]',
			'month' => '[type="month"]',
			'week' => '[type="week"]',
			'time' => '[type="time"]',
			'tel' => '[type="tel"]',
			'number' => '[type="number"]',
			'color' => '[type="color"]',
			'checkbox' => '[type="checkbox"]',
			'radio' => '[type="radio"]',
			'range' => '[type="range"]',
			'file' => '[type="file"]',
			'submit' => '[type="submit"]',
			'image' => '[type="image"]',
			'button' => '[type="button"]',
			'reset' => '[type="reset"]',

			'h' => ':hidden',
			't' => ':text',
			'p' => ':password',
			'c' => ':checkbox',
			'r' => ':radio',
			'f' => ':file',
			's' => ':submit',
			'i' => ':image',
			'b' => ':button',
		],

		'select' => [
			'disabled' => '[disabled.]',

			'd' => ':disabled',
		],

		'menu' => [
			'context' => '[type="context"]',
			'toolbar' => '[type=toolbar]',

			'c' => ':context',
			't' => ':toolbar',
		],

		'html' => [
			'xml' => '[xmlns="http://www.w3.org/1999/xhtml"]'
		],

		'button' => [
			'submit' => '[type="submit"]',
			'reset' => '[type="reset"]',
			'disabled' => '[disabled.]',

			's' => ':submit',
			'r' => ':reset',
			'd' => ':disabled',
		],

		'fieldset' => [
			'disabled' => '[disabled.]',

			'd' => ':disabled',
		],



		'a' => [
			'b' => '[target="_blank"]'
		],
	];

	protected static $emptyElmList = [
		'area',
		'base',
		'br',
		'col',
		'embed',
		'hr',
		'img',
		'input',
		'link',
		'meta',
		'param',
		'source',
		'track',
		'wbr',
	];
	protected static $specialTagList = [
		't' => [],
		'text' => [],

		'c' => [
			'opening' => '<!--',
			'closing' => '-->'
		],
		'comment' => [
			'opening' => '<!--',
			'closing' => '-->'
		],

		'cc:ie6' => [
			'opening' => '<!--[if lte IE 6]>',
			'closing' => '<![endif]-->'
		],
		'cc:ie' => [
			'opening' => '<!--[if IE]>',
			'closing' => '<![endif]-->'
		],
		'cc:noie' => [
			'opening' => '<!--[if !IE]><!-->',
			'closing' => '<!--<![endif]-->'
		],

		'!!!' => [
			'opening' => '<!DOCTYPE html>'
		],

		'?xml' => [
			'opening' => '<?xml version="1.0" encoding="utf-8" ?>'
		],

		'?vya' => [
			'opening' => '<?vya ',
			'closing' => ' ?>'
		],

		'?php' => [
			'opening' => '<?php ',
			'closing' => ' ?>'
		],
	];

	public function __construct(string $emmet = null, string $defaultMode = 'single')
	{
		if (empty($emmet)) $emmet = $this->getConfig('default_tag');
		$this->defaultMode = $defaultMode;
		$this->set($emmet);
	}

	public function set(string $emmet)
	{
		if (preg_match(
			'~[>+^]~',
			preg_replace('~\\{.*?(?<!\\\\)}|\\[.*?(?<!\\\\)]~', '', $emmet)
		)) {
			ee('warn', 'V-yaTag can\'t process relation syntax', 'V-yaTag');
			$emmet = preg_replace(
				'~((?:[^{}[\\]]+|\\{.*?(?<!\\\\)}|\\[.*?(?<!\\\\)])+)[>+^].*$~',
				'$1',
				$emmet
			);
		}

		if (preg_replace('~\\{.*?(?<!\\\\)}~', '', $emmet) === '') { //テキストだけのとき
			$name = 'text';
		} else {
			$emmetXpld = preg_split('~(?=[#.[{])~', $emmet, 2);
			$name = $emmetXpld[0] !== '' ? $emmetXpld[0] : 'div';
			$emmet = $emmetXpld[1] ?? '';

			if ($name{0} === '/') {
				$this->defaultMode = 'closing';
				$name = substr($name, 1);
			}

			preg_match('~^(?<name>[^:]+)(?::(?<optn>.*))?$~', $name, $nameXpld);
			$name = $nameXpld['name'];
			$optn = $nameXpld['optn'] ?? '';
			$optnXpld = explode(':', $optn);

			if (isset(self::$abbrList[$name])) {
				$abbrXpld = preg_split('~(?=[#.[{])~', self::$abbrList[$name], 2);
				$name = ($abbrXpld[0] === '' ? $name : $abbrXpld[0]);
				$emmet = ($abbrXpld[1] ?? '').$emmet;
			}
			if (!empty($optn)) {
				if (isset(self::$optnList[$name])) {
					$optnList = self::$optnList[$name];

					foreach ($optnXpld as $optnXpldIndex) {
						while (isset($optnList[$optnXpldIndex])) {
							if ($optnList[$optnXpldIndex]{0} === ':') {
								$optnXpldIndex = substr($optnList[$optnXpldIndex], 1);
								continue;
							} else {
								$emmet = $optnList[$optnXpldIndex].$emmet;
								break;
							}
						}
					}
				} else {
					$name = $name.':'.$optn;
				}
			}
		}

		if ($name{0} === '!') {
			$this->tagType = VyaTag::TYPE_DECLARATION;
		} elseif ($name{0} === '?') {
			$this->tagType = VyaTag::TYPE_PI;
		} elseif (isset(array_flip(self::$emptyElmList)[$name])) {
			$this->tagType = VyaTag::TYPE_EMPTY;
		} else {
			$this->tagType = VyaTag::TYPE_NORMAL;
		}

		$this->name = $name;

		if ($emmet === '') return; //タグ名だけの時

		if (strpos($emmet, '{') !== false) { //テキスト
			preg_match_all('~\\{(.*?)(?<!\\\\)}~', $emmet, $textMatches);
			$text = end($textMatches[1]);
			$text = str_replace('\\}', '}', $text);
			$this->setText($text);
			$emmet = preg_replace('~\\{.*?\\}~', '', $emmet);
		}

		if (strpos($emmet, '[') !== false) { //属性
			preg_match_all('~\\[(.*?)(?<!\\\\)]~', $emmet, $attrMatches);
			foreach ($attrMatches[1] as $attrBlock) {
				$text = str_replace('\\]', ']', $attrBlock);
				preg_match_all(
					'~(?<name>[^ =\'"]+)(?:=(["\']?)(?<value>[^\\2]*?)\\2)?(?: |$)~',
					$attrBlock,
					$attrList
				);
				foreach ($attrList['name'] as $attrIndex => $attrName) {
					$attrValue = $attrList['value'][$attrIndex];

					if($attrName{-1} === '.' && $attrValue === '') {
						$attrName = substr($attrName, 0, -1);
						$attrValue = $attrName;
					}
					$this->addAttr($attrName, $attrValue);
				}
			}
			$emmet = preg_replace('~\\[.*?\\]~', '', $emmet);
		}

		if (!empty($emmet)) { //ID,クラス
			$emmet = preg_split('~(?<=.)(?=[#\\.])~', $emmet);

			foreach ($emmet as $elemAttr) {
				$elemAttr = preg_split('~(?<=.)~', $elemAttr, 2);
				switch ($elemAttr[0]) {
					case '#': {
						$this->setId($elemAttr[1]);
						break;
					}
					case '.': {
						$this->addClass($elemAttr[1]);
						break;
					}
				}
			}
		}
	}

	public function setDefaultMode(bool $mode) :VyaTag
	{
		$this->isOpened = $mode;
		return $this;
	}

	public function setName(string $newName) :VyaTag
	{
		$this->name = $newName;
		return $this;
	}
	public function getName() :string
	{
		return $this->name;
	}

	public function setId(?string $newId) :VyaTag
	{
		$this->id = $newId;
		return $this;
	}

	public function addClass($newClass) :VyaTag
	{
		if (!empty($newClass)) {
			if (is_array($newClass)) {
				foreach ($newClass as $value) {
					$this->addClass($value);
				}
			} else {
				$this->class[] = $newClass;
			}
		}
		return $this;
	}
	public function remClass(string $remClass) :VyaTag
	{
		$this->class = array_values(array_diff($this->class, [$remClass]));
		return $this;
	}

	public function addStyle(string $newStyleName, string $newStyleValue) :VyaTag
	{
		if (!(empty($newStyleName) || empty($newStyleValue))) {
			$this->style[$newStyleName] = $newStyleValue;
		}
		return $this;
	}
	public function remStyle(string $remStyle) :VyaTag
	{
		$this->style = array_values(array_diff($this->class, [$remStyle]));
		return $this;
	}

	public function addAttr(string $attrName, $attrValue = '') :VyaTag
	{
		if ((string) $attrName !== '') {
			if (is_array($attrName)) {
				$attrList = &$attrName;
				foreach ($attrList as $name => $value) {
					$this->attr[$name] = $value;
				}
			} else {
				$this->attr[$attrName] = $attrValue;
			}
		}
		return $this;
	}
	public function remAttr(string $attrName) :VyaTag
	{
		unset($this->attr[$attrName]);
		return $this;
	}

	public function setText($text) :VyaTag
	{
		$this->text = $text;
		return $this;
	}
	public function getText()
	{
		return $this->text;
	}

	private function genTag($slash) :string
	{
		$id = '';
		if ($this->id !== '') {
			$id = ' id="'.$this->id.'"';
		}

		$class = '';
		if ($this->class !== []) {
			$class = ' class="'.join(' ', $this->class).'"';
		}

		$style = '';
		if (!empty($this->style)) {
			foreach ($this->style as $name => $value) {
				$style .= $name.': '.$value.'; ';
			}
			$style = ' style="'.rtrim($style).'"';
		}

		$attr = '';
		if (!empty($this->attr)) {
			foreach ($this->attr as $name => $value) {
				if ((string) $value !== '') {
					$attr .= ' '.$name.'="'.$value.'"';
				} else {
					$attr .= ' '.$name.($this->getConfig('xhtml_mode') ? '=""' : '');
				}
			}
		}

		return (
			'<'.
			$this->name.
			$id.
			$class.
			$style.
			$attr.
			($slash ? ' /' : '').
			'>'
		);
	}

	private function opening() :string
	{
		if (isset(VyaTag::$specialTagList[$this->name])) {
			return VyaTag::$specialTagList[$this->name]['opening'] ?? '';
		} else {
			return $this->genTag(false);
		}
	}
	private function closing() :string
	{
		if (isset(VyaTag::$specialTagList[$this->name])) {
			return VyaTag::$specialTagList[$this->name]['closing'] ?? '';
		} else {
			return '</'.$this->name.'>';
		}
	}
	private function single() :string
	{
		if ($this->tagType === Vyatag::TYPE_EMPTY) {
			return $this->empty();
		} else {
			return $this->opening().$this->text.$this->closing();
		}
	}

	private function empty() :string
	{
		return $this->genTag($this->getConfig('xhtml_mode'));
	}

	public function print($mode = null) :string
	{
		if ($mode === null) $mode = $this->defaultMode;

		if ($mode === 'auto') {
			$mode = ($this->countPrinted % 2 === 0) ? 'opening' : 'closing';
			++$this->countPrinted;
		}

		switch ($mode) {
			case 'opening':
			case 'closing':
			case 'single':
				return $this->$mode();
				break;

			case 'text':
				return $this->getText();
				break;

			default:
				return $this->single();
				break;
		}
	}

	public function __toString()
	{
		return $this->print();
	}

	public function __debugInfo()
	{
		return $this->debugInfoProcessor([
			'name',
			'id',
			'class',
			'style',
			'attr',
			'text',
			'isOpened'
		]);
	}
}

/*||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||*\
|*|
|*|  トレイト
|*|
\*||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||*/

trait DebugInfoProcessor {
	protected function debugInfoProcessor($propList)
	{
		$return = [];

		foreach ($propList as $propData) {
			if (!is_array($propData)) {
				$propData = [
					'name' => $propData
				];
			}
			$propName = $propData['name'];
			if (isset($this->$propName)) {
				if (
					(
						$propData['if'] ??
						function ($prop) {
							return !empty($prop);
						}
					)($this->$propName)
				) {
					$return[$propData['display'] ?? $propName] = (
						$propData['return'] ??
						function ($prop) {return $prop;}
					)($this->$propName);
				}
			}
		}

		return $return;
	}
}

trait VyaConfig {
	/*
	 * setのとき
	 * - 配列 -> 追加 OR 上書き
	 * - 非配列 -> 前の値を'_'にうつしてから追加
	 *
	 * getのとき
	 * - 配列 -> '_'を返す
	 * - 非配列 -> そのまま
	 *
	 * 宣言時に'_'入って無ければgetしたとき配列をそのまま返すかエラー吐くか
	 */

	private static $globalConfig = [];
	private $localConfig = [];

	public function setConfig(
		string $nameList,
		$value = 'default'
	) :Vyabase
	{
		self::_configSetter($this->localConfig, $nameList, $value, false);
		return $this;
	}
	public static function setGlobalConfig(
		string $nameList,
		$value = 'default'
	) :bool
	{
		return self::_configSetter(static::$globalConfig, $nameList, $value, true);
	}
	private static function _configSetter(
		array &$config,
		string $nameList,
		$value,
		bool $isGrobal
	) :bool
	{
		$nameList = explode('.', $nameList);

		$current = &$config;
		$parent = &$config;
		foreach ($nameList as $name) {
			$parent = &$current;
			$current = &$current[$name];
		}

		if (isset($current['_'])) {
			$parent = &$current;
			$current = &$current['_'];
		}

		$isNotValueDefault = $value !== 'default';
		if ($isNotValueDefault) {
			$current = $value;
		} else {
			if ($isGrobal) {
				ee('error', '"default" can\'t be set for global confing');
			} else {
				unset($parent[$name]);
			}
		}

		return $isNotValueDefault;
	}

	public function getConfig(?string $nameList = null)
	{
		return self::_configGetter(
			$nameList,
			x_array_merge_recursive(self::$globalConfig, $this->localConfig)
		);
	}
	public static function getGlobalConfig(?string $nameList = null)
	{
		return self::_configGetter(
			$nameList,
			self::$globalConfig
		);
	}
	private static function _configGetter(
		?string $nameList = null,
		array $config
	)
	{
		if ($nameList === null) return $config;

		$nameList = explode('.', $nameList);
		$nameTarget = array_pop($nameList);
		foreach ($nameList as $name) {
			$config = $config[$name];
			$history[$name] = &$config;

			if (isset($config['_']) && (bool) $config['_'] === false) return false;
		}
		$config = $config[$nameTarget];

		if (is_array($config)) $config = $config['_'] ?? $config;

		return $config;
	}
}

/*||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||*\
|*|
|*|  その他
|*|
\*||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||*/

function e(
	string $type,
	string $text,
	string $from = null,
	string ...$fromOther
)
{
	$type = strtoupper(!empty($type) ? $type : 'V-yaBase');
	$fromList = array_merge([$from], $fromOther);
	$e = new \Exception('['.implode('] [', $fromList).'] ['.$type.'] '.$text);
	switch ($type) {
		case 'FATAL':
			$level = 5;
			break;
		case 'STRICT':
			$level = 4;
			break;
		case 'ERROR':
			$level = 3;
			break;
		case 'WARN':
			$level = 2;
			break;
		case 'NOTICE':
			$level = 1;
			break;
		default:
			ee('error', 'Unknown exception type "'.$type.'"');
			$level = 0;
			break;
	}
	if ($level >= 4) {
		die($e);
	} elseif ($level >= 1) { //そのうち表示レベルを設定できるようにする
		return $e;
	} else {
		return;
	}
}

function ee(
	string $type,
	string $text,
	string $from = null,
	string ...$fromAnothor
)
{
	echo e($type, $text, $from, ...$fromAnothor), PHP_EOL;
}

function execute(string $path)
{
	ob_start();
	include $path;
	return ob_get_clean();
}

function x_array_merge_recursive(array ...$arrayList) {
	$array_base = array_shift($arrayList);

	foreach ($arrayList as $array) {
		foreach ($array as $key => $index) {
			if (isset($array_base[$key])) {
				if (is_array($array_base[$key])) {
					if (is_array($index)) {
						$array_base[$key] = x_array_merge_recursive($array_base[$key], $index);
					} else {
						$array_base[$key]['_'] = $index;
					}
				} else {
					if (is_array($index)) {
						$index['_'] = $array_base[$key];
					} else {
						$array_base[$key] = $index;
					}
				}
			} else {
				$array_base[$key] = $index;
			}
		}
	}

	return $array_base;
}

function indent2array(string $text, string $indentStr = null) :array
{
	//改行コード統一
	$text = str_replace("\r", '', $text);
	$text = trim($text, "\n");

	//空白だけの行をトリム
	$text = preg_replace('~^[[:blank:]]*$~m', '', $text);

	//インデント文字検出
	if ($indentStr === null) {
		preg_match('~\\n+(?<indent>[[:blank:]]+?)\\S~', $text, $indentStrDetected);
		$indentStr = $indentStrDetected['indent'] ?? '';
	}

	//インデントしてない時は単に改行でexplodeして返す
	if ($indentStr === '') {
		return explode("\n", preg_replace('~^[[:blank:]]+~m', '', $text));
	}

	return _indent2array($text, $indentStr);
}
function _indent2array(string $text, string $indentStr = null) :array
{
	$result = [];

	$indentStrQtd = preg_quote($indentStr);

	//先頭の余分なインデントを除去
	preg_match('~^(?:'.$indentStrQtd.')+~', $text, $extraIndents);
	if (isset($extraIndents[0])) {
		$text = preg_replace('~^'.$extraIndents[0].'~m', '', $text);
	}

	//インデントに囲まれた空白行でsplitしないようにインデント文字追加
	$text = preg_replace('~(?=^'.$indentStrQtd.$indentStrQtd.')~m', $indentStr, $text);

	//インデントが始まる直前の行でsplit
	$textXpld = preg_split('~\\n(?!\\n|'.$indentStrQtd.')~', $text);
	$nextText = null;
	foreach ($textXpld as $line) {
		if (preg_match('~\\n((?:'.$indentStrQtd.')+?)\\S~', $line, $indentStrNew)) { //インデントしてるとき
			// 次の配列になる部分とそうでない部分とで分ける
			$lineXpld = preg_split('~\\n(?!\\n)~', $line, 2);

			$linem = explode("\n", $lineXpld[0]);
			$nextText = $lineXpld[1];
		} else {
			$linem = explode("\n", $line);
		}

		foreach ($linem as $line) {
			$result[] = trim($line);
		}

		if ($nextText !== null) {
			$result[] = _indent2array($nextText, $indentStr);
			$nextText = null;
		}
	}
	return $result;
}

function array2emmet(array $array) :string
{
	$result = '';
	foreach ($array as $key => $index) {
		if (is_array($index)) continue;
		if (isset($array[$key + 1]) && is_array($array[$key + 1])) {
			$result .= '+('.$index.'>'.array2emmet($array[$key + 1]).')';
		} else {
			if (strpos($index, '>') !== false) $index = '('.$index.')';
			if ($index !== '') $result .= '+'.$index;
		}
	}
	return ltrim($result, '+');
}

function array_replace_recursive_once(
	$needle,
	$replacement,
	array &$search
) :void
{
	static $_isFirst = true;
	static $_replaced = false;
	$isFirst = false;
	if ($_isFirst) {
		$isFirst = true;
		$_isFirst = false;
	}

	foreach ($search as $key => &$index) {
		if (is_array($index)) {
			array_replace_recursive_once($needle, $replacement, $index);
			continue;
		}
		if ($_replaced) break;
		if ($index === $needle) {
			if (!$_replaced) {
				if (!is_array($replacement)) $replacement = [$replacement];
				$search = array_merge(
					array_slice($search, 0, $key - 1),
					$replacement,
					array_slice($search, $key + 1)
				);
				$_replaced = true;
			} else break;
		}
	}

	if ($isFirst) {
		$_isFirst = true;
	}
}

function emmet2html(string $emmet, &$refunds = null) :string
{
	return (string) (new Vyabase)->set(Vyabase::emmet($emmet, $refunds));
}

/*||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||*\
|*|
|*|  コンフィグ
|*|
\*||||||||||||||||||||||||||||||||||||||||||||||||||||||||||||*/

$vya_config = VYA_CONFIG;

if (file_exists(__DIR__.'/vyabase_config.json')) {
	$vya_config = x_array_merge_recursive(
		$vya_config,
		json_decode(
			file_get_contents(__DIR__.'/vyabase_config.json'),
			true
		) ?: []
	);
}

function _configSetter(
	string $target,
	array $configList,
	?string $configPath = null
) :void {
	foreach ($configList as $configKey => $configValue) {
		if (!empty($configPath)) $configKey = $configPath.'.'.$configKey;

		if (is_array($configValue)) {
			_configSetter($target, $configValue, $configKey);
		} else {
			([__NAMESPACE__.'\\'.$target, 'setGlobalConfig'])($configKey, $configValue);
		}
	}
};

_configSetter('Vyabase', $vya_config['vyabase'] ?? []);
_configSetter('VyaTag', $vya_config['vyatag'] ?? []);

ini_set('zend.multibyte', '1');
ini_set('zend.script_encoding', 'UTF-8');

if (Vyabase::getGlobalConfig('expose_vyabase')) {
	header('X-Powered-By: Vya-Base/0.0.0', false);
}
