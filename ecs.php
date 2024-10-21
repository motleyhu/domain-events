<?php

declare(strict_types=1);

use Motley\EasyCodingStandard\SetList;
use PHP_CodeSniffer\Standards\Generic\Sniffs\NamingConventions\UpperCaseConstantNameSniff;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\Commenting\ClassCommentSniff;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->cacheDirectory('.ecs-cache');
    $ecsConfig->paths([__DIR__ . '/src', __DIR__ . '/tests']);

    $ecsConfig->sets([SetList::MOTLEY]);

    $ecsConfig->skip([
        ClassCommentSniff::class,
    ]);
};
