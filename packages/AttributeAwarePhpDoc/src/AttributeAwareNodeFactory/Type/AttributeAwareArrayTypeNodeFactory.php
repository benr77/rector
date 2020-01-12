<?php

declare(strict_types=1);

namespace Rector\AttributeAwarePhpDoc\AttributeAwareNodeFactory\Type;

use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use Rector\AttributeAwarePhpDoc\Ast\Type\AttributeAwareArrayTypeNode;
use Rector\AttributeAwarePhpDoc\Contract\AttributeNodeAwareFactory\AttributeNodeAwareFactoryInterface;
use Rector\BetterPhpDocParser\Contract\PhpDocNode\AttributeAwareNodeInterface;

final class AttributeAwareArrayTypeNodeFactory implements AttributeNodeAwareFactoryInterface
{
    public function getOriginalNodeClass(): string
    {
        return ArrayTypeNode::class;
    }

    public function isMatch(Node $node): bool
    {
        return is_a($node, ArrayTypeNode::class, true);
    }

    /**
     * @param ArrayTypeNode $node
     */
    public function create(Node $node): AttributeAwareNodeInterface
    {
        return new AttributeAwareArrayTypeNode($node->type);
    }
}
