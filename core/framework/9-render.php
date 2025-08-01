<?php
variable('known-extensions', [
	'prefixes' => ['jpg'],
	'core' => ['php', 'md', 'tsv', 'html', 'txt'],
	'suffixes' => ['pdf'],
]);

function remove_extension($file) {
	if (!($core = variable('replace_extensions'))) {
		$extns = subVariable('known-extensions', 'core');
		$core = [];
		foreach ($extns as $extn) {
			$core['.' . $extn] = '';
		}
		variable('replace_extensions', $core);
	}

	return replaceItems($file, $core);
}

//extensions will render multiple. Can be - explicit / prefixes / core / suffixes / array[]
function renderAnyFile($fwe, $settings = []) {
	$extensions = valueIfSet($settings, 'extensions', 'explicit');
	$fail = valueIfSet($settings, 'fail', true);
	$returnOnFirst = valueIfSet($settings, 'return-on-first', true); //adapt when implementing for archives

	$inUseValues = [ 'extensions' => $extensions, 'fail' => $fail, 'return-on-first' => $returnOnFirst ];
	$failParams = ['$fwe (file with/without extension)' => $fwe, 'in-use-values' => $inUseValues, 'settings' => $settings];
	$known = variable('known-extensions');

	if ($extensions == 'explicit') {
		$exists = disk_file_exists($fwe);
		if ($exists) {
			_renderSingleFile($fwe);
			return true;
		}

		if ($fail) raiseParameterError('FILE NOT FOUND', $failParams);
	} else if (is_array($extensions)) {
		//fail never applies here
		foreach($extensions as $extension) {
			$fpe = $fwe . $extension; //name PLUS extension
			$exists = disk_file_exists($fpe);
			if ($exists) {
				autoRender($fpe);
				if ($returnOnFirst) return true;
			}
		}
	} else if (array_key_exists($extensions, $known)) {
		$newSettings = array_merge($inUseValues, ['extensions' => $known[$extensions]]);
		return renderAnyFile($fwe, $newSettings);
	} else if ($extensions == 'all') {
		foreach ($known as $key => $item) {
			$newSettings = array_merge($inUseValues, ['extensions' => $key]);
			$result = renderAnyFile($fwe, $newSettings);
			if ($result && $returnOnFirst) return true;
		}
	} else {
		parameterError('CRITICAL - NOT SUPPORTED', $failParams);
		exit;
	}
}

//internal method - expects file to exist 
function _renderSingleFile($file, $extension = 'auto') {
	if ($extension == 'php' || endsWith($file, '.php')) {
		disk_include_once($file);
		return;
	}

	//TODO: Copy media + pdf logic from archives.yieldmore.org

	renderAny($file);
}

function renderPlainHtml($file) {
	echo disk_file_get_contents($file);
}

function renderExcerpt($file, $link, $prefix = '', $echo = true) {
	$prefix = $prefix ? renderMarkdown($prefix) : '';
	$raw = renderAny($file, ['excerpt' => true, 'echo' => false, 'markdown' => endsWith($file, '.md')]);

	$result = $prefix . _excludeFromGoogleSearch($raw)
		. '<a class="read-more" href="' . $link . '">Read More&hellip;</a>';
	
	if (!$echo) return $result;
	echo $result;
}

DEFINE('GOOGLEOFF', '<!--googleoff: all-->');
DEFINE('GOOGLEON', '<!--googleon: all-->');

function _excludeFromGoogleSearch($raw) {
	return GOOGLEOFF
		. variable('nl') . $raw
		. variable('nl') . GOOGLEON
		. variable('2nl');
}

function renderOnlyMarkdownOrRaw($raw, $wantsMD, $settings = []) {
	return $wantsMD ? renderSingleLineMarkdown($raw, $settings) : $raw; //so we can use inline in code
}

function renderMarkdown($raw, $settings = []) {
	$settings['markdown'] = true;
	return _renderImplementation($raw, $settings);
}

function returnLines($raw) {
	return renderMarkdown($raw, ['echo' => false]);
}

function returnLinesNoParas($raw) {
	return renderSingleLineMarkdown($raw, ['echo' => false, 'strip-paragraph-tag' => true]);
}

function returnLine($raw) {
	return renderSingleLineMarkdown($raw, ['echo' => false]);
}

function renderSingleLineMarkdown($raw, $settings = []) {
	return renderMarkdown($raw, array_merge($settings, ['strip-paragraph-tag' => true]));
}

function renderMarkdownSection($h1, $raw, $settings = []) {
	echo '<section><h1>' . $h1 . '</h1>' . variable('nl');

	if (isset($settings['excerpt']))
		renderExcerpt($raw, $settings['link'], $settings);
	else
		renderMarkdown($raw, $settings);

	echo variable('nl') . '</section>' . variable('2nl');
}

function renderAny($file, $settings = []) {
	return _renderImplementation($file, $settings);
}

//_ denotees its not to be called from outside - see flavours above + remove deprecated
function _renderImplementation($fileOrRaw, $settings) {
	if (endsWith($fileOrRaw, 'family-tree.md')) {
		includeFeature('family-tree');
		renderFamilyTree($fileOrRaw); //only echoes for now
		return;
	}

	if (isset($settings['markdown'])) {
		//$settings['strip-paragraph-tag'] = true;
		$settings['clear-markdown-start'] = true;
		$fileOrRaw = (disk_file_exists($fileOrRaw) ? '' : variable('markdownStartTag')) . $fileOrRaw;
	}

	//TODO: Consider an explicit-load so file exists can be avoided?
	//debug('render.php - _renderImplementation', ['verbose params!', $fileOrRaw]);

	$endsWithMd = false;
	$raw = $fileOrRaw; $fileName = '[RAW]';
	if ($wasFile = disk_file_exists($fileOrRaw)) {
		$fileName = $fileOrRaw;
		$endsWithMd = endsWith($fileOrRaw, '.md');
		$raw = disk_file_get_contents($fileOrRaw);
	}

	$replaces = valueIfSet($settings, 'replaces', []);
	$echo = valueIfSet($settings, 'echo', true);
	$excerpt = valueIfSet($settings, 'excerpt', false);
	$no_processing = valueIfSet($settings, 'raw', false) || contains($raw, '<!--no-processing-->') || do_md_in_parser($raw);

	if ($excerpt) $raw = explode('<!--more-->', $raw)[0];

	if (function_exists('site_render_content')) $raw = site_render_content($raw);

	$replacesParams = isset($settings['replaces']) ? $settings['replaces'] : [];
	$plainReplaces = isset($settings['plainReplaces']) ? $settings['plainReplaces'] : [];
	$builtinReplaces = [
		'site-assets' => variable(assetKey(SITEASSETS)),
		'site-assets-images' => variable(assetKey(SITEASSETS)) . 'images/',
		'app' => variable('app'),
		'app-assets' => variable(assetKey(COREASSETS)),
	];

	$raw = replaceItems($raw, $replacesParams, '%');
	$raw = replaceItems($raw, $plainReplaces, '');
	$raw = replaceItems($raw, $builtinReplaces, '##');

	if ($wasFile && variable('autofix-encoding')) $raw = simplify_encoding($raw);

	/**** rethink this
	if (variable('node') && variable('section')) {
		$assetsUrl = fileUrl('url') . variable('section') . '/assets/' . variable('node') . '/';
		$assetsFol = SITEPATH . '/'. variable('section') . '/assets/' . variable('node') . '/';
		variables($assetsVars = ['assetsUrl' => $assetsUrl, 'assetsFol' => $assetsFol]);
		$raw = replaceItems($raw, $assetsVars, '%');
	}

	if ($vars = variable('node-replaces')) $raw = replaceItems($raw, $vars, '%', true);
	*/
	if ($svars = variable('siteReplaces')) $raw = replaceItems($raw, $svars, '%', true);

	$markdownStart = variable('markdownStartTag');
	$autopStart = variable('autopStart');
	$param1IsPageTag = '<!--node-item-is-page-->';
	$param1IsPage = contains($raw, $param1IsPageTag);

	$autop = $raw != '' && startsWith($raw, $autopStart);
	$md = $raw != '' && ($raw[0] == '#' || startsWith($raw, $markdownStart));
	$engageContent = false;

	if ($no_processing) {
		$output = $raw;
	} else if ($autop || ($endsWithMd && variable('autop-for-markdown'))) {
		//TODO: @<team> temp for Sarath site which should use txt (autop) ideally
		$output = wpautop($raw);
	} else {
		$inProgress = '<!--render-processing-->';
		if (engage_until_eof($raw)) {
			$engageBits = explode(ENGAGESTART, $raw);
			$raw = $engageBits[0];
			$engageContent = $engageBits[1];
		}

		if (is_engage($raw) && !contains($raw, $inProgress)) {
			runFeature('engage');
			$settings['use-content-box'] = false;
			$output = _renderEngage(getPageName(), $raw . $inProgress, true, false);
		} else {
			$ai = contains($raw, FROM_GEMINI_AI);
			if ($ai) {
				runFrameworkFile('parser');
				$raw = processAI($raw, 'gemini');
			}

			$output = $md || $endsWithMd ? markdown($raw) : wpautop($raw);
		}
	}

	if (contains($output, '%menu-for-this'))
		$output = _renderAllInPageMenus($output);

	$output = runAllMacros($output);

	if (contains($raw, '<!--composite-work-->') && !(variable('is-in-directory'))) {
		runFrameworkFile('parser');
		$prepend = getWorkSettings($fileName);
		$param1IsPage = $param1IsPage || contains($prepend, $param1IsPageTag);
		$output = parseCompositeWork($prepend . $output, $param1IsPage);
	}

	$output = replaceHtml($output);

	if (!isset($settings['dont-prepare-links']))
		$output = prepareLinks($output); //if doing before markdown then this gets messed up

	if (isset($settings['strip-paragraph-tag']))
		$output = strip_paragraph($output);

	if (isset($settings['clear-markdown-start']))
		$output = str_replace(variable('markdownStart'), '', $output);

	if (contains($output, '%fileName%'))
		$output = replaceItems($output, ['%fileName%' => '<u>EDIT FILE:</u> ' .
			replaceItems($fileName, [SITEPATH => '', '//' => '/'])]);

	if (isset($settings['wrap-in-section']))
		$output = '<section>' . variable('nl') . $output . variable('nl') . '</section>' . variable('2nl');

	if (isset($settings['use-content-box']) && $settings['use-content-box'])
		$output = cbWrapAndReplaceHr($output);

	if (isset($settings['heading'])) $output = h2($settings['heading'] . currentLevel(), 'amadeus-icon', true) . NEWLINES2 . $output;

	if ($engageContent) {
		runFeature('engage');
		$settings['use-content-box'] = false;
		$output .= _renderEngage(getPageName(), $engageContent . $inProgress, true, false);
	}

	if (!$echo) return $output;
	echo $output;
}

DEFINE('FROM_GEMINI_AI', '<!--exported-from-gemini-ai-->');

function peekAtMainFile($file) {
	$raw = disk_file_get_contents($file);
	$ai = contains($raw, FROM_GEMINI_AI);
	if (!$ai) return;
	
	add_body_class('with-ai has-gemini-ai has-prompts');
}

function renderRichPage($sheetFile, $groupBy = 'section', $templateName = 'home') {
	variable('home', getSheet($sheetFile, $groupBy));
	$call = variable('theme_folder') . $templateName . '.php';
	disk_include_once($call);
}

function is_engage($raw) {
	return contains($raw, ' //engage-->') || contains($raw, '<!--ENGAGE-->');
}

DEFINE('ENGAGESTART', '<!--start-engage-->');
function engage_until_eof($raw) {
	return contains($raw, ENGAGESTART);
}

function do_md_in_parser($raw) {
	return contains($raw, '<!--markdown-when-processing-->');
}
