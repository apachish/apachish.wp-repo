<?php

use Apachish\WpRepo\Support\RepoName;

it('reduces pasted repo references to owner/repo', function (string $input, string $expected) {
    expect(RepoName::normalize($input))->toBe($expected);
})->with([
    ['apachish/wc-bulk-product-editor-pro', 'apachish/wc-bulk-product-editor-pro'],
    ['apachish/wc-bulk-product-editor-pro.git', 'apachish/wc-bulk-product-editor-pro'],
    ['https://github.com/apachish/wc-bulk-product-editor-pro', 'apachish/wc-bulk-product-editor-pro'],
    ['https://github.com/apachish/wc-bulk-product-editor-pro.git/', 'apachish/wc-bulk-product-editor-pro'],
    ['git@github.com:apachish/wc-bulk-product-editor-pro.git', 'apachish/wc-bulk-product-editor-pro'],
    ['  https://gitlab.com/group/sub/project.git  ', 'group/sub/project'],
]);
