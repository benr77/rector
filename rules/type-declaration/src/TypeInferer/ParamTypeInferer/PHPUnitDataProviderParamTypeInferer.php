<?php

declare(strict_types=1);

namespace Rector\TypeDeclaration\TypeInferer\ParamTypeInferer;

use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\BetterPhpDocParser\ValueObject\PhpDocNode\PHPUnit\PHPUnitDataProviderTagValueNode;
use Rector\Core\Exception\ShouldNotHappenException;
use Rector\Core\PhpParser\Node\BetterNodeFinder;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\NodeTypeResolver\NodeTypeResolver;
use Rector\NodeTypeResolver\PHPStan\Type\TypeFactory;
use Rector\TypeDeclaration\Contract\TypeInferer\ParamTypeInfererInterface;

final class PHPUnitDataProviderParamTypeInferer implements ParamTypeInfererInterface
{
    /**
     * @var BetterNodeFinder
     */
    private $betterNodeFinder;

    /**
     * @var NodeTypeResolver
     */
    private $nodeTypeResolver;

    /**
     * @var TypeFactory
     */
    private $typeFactory;

    /**
     * @var PhpDocInfoFactory
     */
    private $phpDocInfoFactory;

    public function __construct(
        BetterNodeFinder $betterNodeFinder,
        TypeFactory $typeFactory,
        PhpDocInfoFactory $phpDocInfoFactory
    ) {
        $this->betterNodeFinder = $betterNodeFinder;
        $this->typeFactory = $typeFactory;
        $this->phpDocInfoFactory = $phpDocInfoFactory;
    }

    /**
     * Prevents circular reference
     * @required
     */
    public function autowirePHPUnitDataProviderParamTypeInferer(NodeTypeResolver $nodeTypeResolver): void
    {
        $this->nodeTypeResolver = $nodeTypeResolver;
    }

    public function inferParam(Param $param): Type
    {
        $dataProviderClassMethod = $this->resolveDataProviderClassMethod($param);
        if (! $dataProviderClassMethod instanceof ClassMethod) {
            return new MixedType();
        }

        $parameterPosition = $param->getAttribute(AttributeKey::PARAMETER_POSITION);
        if ($parameterPosition === null) {
            return new MixedType();
        }

        /** @var Return_[] $returns */
        $returns = $this->betterNodeFinder->findInstanceOf((array) $dataProviderClassMethod->stmts, Return_::class);
        if ($returns !== []) {
            return $this->resolveReturnStaticArrayTypeByParameterPosition($returns, $parameterPosition);
        }

        /** @var Yield_[] $yields */
        $yields = $this->betterNodeFinder->findInstanceOf((array) $dataProviderClassMethod->stmts, Yield_::class);
        return $this->resolveYieldStaticArrayTypeByParameterPosition($yields, $parameterPosition);
    }

    private function resolveDataProviderClassMethod(Param $param): ?ClassMethod
    {
        $phpDocInfo = $this->getFunctionLikePhpDocInfo($param);

        $phpUnitDataProviderTagValueNode = $phpDocInfo->getByType(PHPUnitDataProviderTagValueNode::class);
        if (! $phpUnitDataProviderTagValueNode instanceof PHPUnitDataProviderTagValueNode) {
            return null;
        }

        $classLike = $param->getAttribute(AttributeKey::CLASS_NODE);
        if (! $classLike instanceof Class_) {
            return null;
        }

        return $classLike->getMethod($phpUnitDataProviderTagValueNode->getMethodName());
    }

    /**
     * @param Return_[] $returns
     */
    private function resolveReturnStaticArrayTypeByParameterPosition(array $returns, int $parameterPosition): Type
    {
        $paramOnPositionTypes = [];

        foreach ($returns as $classMethodReturn) {
            if (! $classMethodReturn->expr instanceof Array_) {
                continue;
            }

            $type = $this->getTypeFromClassMethodReturn($classMethodReturn->expr);

            if (! $type instanceof ConstantArrayType) {
                return $type;
            }

            foreach ($type->getValueTypes() as $position => $valueType) {
                if ($position !== $parameterPosition) {
                    continue;
                }

                $paramOnPositionTypes[] = $valueType;
            }
        }

        if ($paramOnPositionTypes === []) {
            return new MixedType();
        }

        return $this->typeFactory->createMixedPassedOrUnionType($paramOnPositionTypes);
    }

    private function getTypeFromClassMethodReturn(Array_ $classMethodReturnArrayNode): Type
    {
        $arrayTypes = $this->nodeTypeResolver->resolve($classMethodReturnArrayNode);

        // impossible to resolve
        if (! $arrayTypes instanceof ConstantArrayType) {
            return new MixedType();
        }

        // nest to 1 item
        $arrayTypes = $arrayTypes->getValueTypes()[0];

        // impossible to resolve
        if (! $arrayTypes instanceof ConstantArrayType) {
            return new MixedType();
        }

        return $arrayTypes;
    }

    /**
     * @param Yield_[] $yields
     */
    private function resolveYieldStaticArrayTypeByParameterPosition(array $yields, int $parameterPosition): Type
    {
        $paramOnPositionTypes = [];

        foreach ($yields as $classMethodYield) {
            if (! $classMethodYield->value instanceof Array_) {
                continue;
            }

            $type = $this->getTypeFromClassMethodYield($classMethodYield->value);

            if (! $type instanceof ConstantArrayType) {
                return $type;
            }

            foreach ($type->getValueTypes() as $position => $valueType) {
                if ($position !== $parameterPosition) {
                    continue;
                }

                $paramOnPositionTypes[] = $valueType;
            }
        }

        if ($paramOnPositionTypes === []) {
            return new MixedType();
        }

        return $this->typeFactory->createMixedPassedOrUnionType($paramOnPositionTypes);
    }

    private function getTypeFromClassMethodYield(Array_ $classMethodYieldArrayNode): Type
    {
        $arrayTypes = $this->nodeTypeResolver->resolve($classMethodYieldArrayNode);

        // impossible to resolve
        if (! $arrayTypes instanceof ConstantArrayType) {
            return new MixedType();
        }

        return $arrayTypes;
    }

    private function getFunctionLikePhpDocInfo(Param $param): PhpDocInfo
    {
        $parent = $param->getAttribute(AttributeKey::PARENT_NODE);
        if (! $parent instanceof FunctionLike) {
            throw new ShouldNotHappenException();
        }

        return $this->phpDocInfoFactory->createFromNodeOrEmpty($parent);
    }
}
