<?php

declare(strict_types=1);

namespace Rector\PHPStanStaticTypeMapper\TypeAnalyzer;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Stmt;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Generic\GenericClassStringType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;
use Rector\NodeTypeResolver\NodeTypeCorrector\GenericClassStringTypeCorrector;

final class UnionTypeCommonTypeNarrower
{
    /**
     * Key = the winner
     * Array = the group of types matched
     *
     * @var array<class-string<Node|\PHPStan\PhpDocParser\Ast\Node>, array<class-string<Node|\PHPStan\PhpDocParser\Ast\Node>>>
     */
    private const PRIORITY_TYPES = [
        BinaryOp::class => [BinaryOp::class, Expr::class],
        Expr::class => [Node::class, Expr::class],
        Stmt::class => [Node::class, Stmt::class],
        'PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode' => [
            'PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode',
            'PHPStan\PhpDocParser\Ast\Node',
        ],
    ];

    /**
     * @var GenericClassStringTypeCorrector
     */
    private $genericClassStringTypeCorrector;

    public function __construct(GenericClassStringTypeCorrector $genericClassStringTypeCorrector)
    {
        $this->genericClassStringTypeCorrector = $genericClassStringTypeCorrector;
    }

    public function narrowToSharedObjectType(UnionType $unionType): ?ObjectType
    {
        $sharedTypes = $this->narrowToSharedTypes($unionType);

        if ($sharedTypes !== []) {
            foreach (self::PRIORITY_TYPES as $winningType => $groupTypes) {
                if (array_intersect($groupTypes, $sharedTypes) === $groupTypes) {
                    return new ObjectType($winningType);
                }
            }

            $firstSharedType = $sharedTypes[0];
            return new ObjectType($firstSharedType);
        }

        return null;
    }

    /**
     * @return GenericClassStringType|UnionType
     */
    public function narrowToGenericClassStringType(UnionType $unionType): Type
    {
        $availableTypes = [];

        foreach ($unionType->getTypes() as $unionedType) {
            if ($unionedType instanceof ConstantStringType) {
                $unionedType = $this->genericClassStringTypeCorrector->correct($unionedType);
            }

            if (! $unionedType instanceof GenericClassStringType) {
                return $unionType;
            }

            $genericClassStrings = [];
            if ($unionedType->getGenericType() instanceof ObjectType) {
                $parentClassReflections = $this->resolveClassParentClassesAndInterfaces($unionedType->getGenericType());
                foreach ($parentClassReflections as $classReflection) {
                    $genericClassStrings[] = $classReflection->getName();
                }
            }

            $availableTypes[] = $genericClassStrings;
        }

        $genericClassStringType = $this->createGenericClassStringType($availableTypes);
        if ($genericClassStringType instanceof GenericClassStringType) {
            return $genericClassStringType;
        }

        return $unionType;
    }

    /**
     * @return string[]
     */
    private function narrowToSharedTypes(UnionType $unionType): array
    {
        $availableTypes = [];

        foreach ($unionType->getTypes() as $unionedType) {
            if (! $unionedType instanceof ObjectType) {
                return [];
            }

            $typeClassReflections = $this->resolveClassParentClassesAndInterfaces($unionedType);
            $typeClassNames = [];
            foreach ($typeClassReflections as $classReflection) {
                $typeClassNames[] = $classReflection->getName();
            }

            $availableTypes[] = $typeClassNames;
        }

        return $this->narrowAvailableTypes($availableTypes);
    }

    /**
     * @return ClassReflection[]
     */
    private function resolveClassParentClassesAndInterfaces(ObjectType $objectType): array
    {
        $classReflection = $objectType->getClassReflection();
        if (! $classReflection instanceof ClassReflection) {
            return [];
        }

        // put earliest interfaces first
        $implementedInterfaceClassReflections = array_reverse($classReflection->getInterfaces());

        /** @var ClassReflection[] $parentClassAndInterfaceReflections */
        $parentClassAndInterfaceReflections = array_merge(
            $implementedInterfaceClassReflections,
            $classReflection->getParents()
        );

        return array_filter($parentClassAndInterfaceReflections, function (ClassReflection $classReflection): bool {
            return ! $classReflection->isBuiltin();
        });
    }

    /**
     * @param string[][] $availableTypes
     * @return string[]
     */
    private function narrowAvailableTypes(array $availableTypes): array
    {
        /** @var string[] $sharedTypes */
        $sharedTypes = array_intersect(...$availableTypes);

        return array_values($sharedTypes);
    }

    /**
     * @param string[][] $availableTypes
     */
    private function createGenericClassStringType(array $availableTypes): ?GenericClassStringType
    {
        $sharedTypes = $this->narrowAvailableTypes($availableTypes);

        if ($sharedTypes !== []) {
            foreach (self::PRIORITY_TYPES as $winningType => $groupTypes) {
                if (array_intersect($groupTypes, $sharedTypes) === $groupTypes) {
                    return new GenericClassStringType(new ObjectType($winningType));
                }
            }

            $firstSharedType = $sharedTypes[0];
            return new GenericClassStringType(new ObjectType($firstSharedType));
        }

        return null;
    }
}
