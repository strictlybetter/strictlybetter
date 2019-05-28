<?php

function escapeLike(string $value, string $char = '\\')
{
	return str_replace(
		[$char, '%', '_'],
		[$char.$char, $char.'%', $char.'_'],
		$value
	);
}

function make_format_list()
{
	// Make list of supported formats
	$formatlist = ['' => 'Any Format'];
	foreach (App\Card::$formats as $formatname) {
		$formatlist[$formatname] = ucfirst($formatname);
	}
	return $formatlist;
}