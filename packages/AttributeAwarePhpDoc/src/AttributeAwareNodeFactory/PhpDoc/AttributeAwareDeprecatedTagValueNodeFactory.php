<?php

declare(strict_types=1);

namespace Rector\AttributeAwarePhpDoc\AttributeAwareNodeFactory\PhpDoc;

final class AttributeAwareDeprecatedTagValueNodeFactory implements \Rector\AttributeAwarePhpDoc\Contract\AttributeNodeAwareFactory\AttributeNodeAwareFactoryInterface
{
    public function getOriginalNodeClass(): string
    {
        return \PHPStan\PhpDocParser\Ast\PhpDoc\DeprecatedTagValueNode::class;
    }

    public function isMatch(\PHPStan\PhpDocParser\Ast\Node $node): bool
    {
        return is_a($node, \PHPStan\PhpDocParser\Ast\PhpDoc\DeprecatedTagValueNode::class, true);
    }

    /**
     * @param \PHPStan\PhpDocParser\Ast\PhpDoc\DeprecatedTagValueNode $node
     */
    public function create(
        \PHPStan\PhpDocParser\Ast\Node $node
    ): \Rector\BetterPhpDocParser\Contract\PhpDocNode\AttributeAwareNodeInterface {
        return new \Rector\AttributeAwarePhpDoc\Ast\PhpDoc\AttributeAwareDeprecatedTagValueNode($node->description);
    }
}
