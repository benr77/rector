<?php

namespace Rector\DependencyInjection\Tests\Rector\Property\AnnotatedPropertyInjectToConstructorInjectionRector\Fixture;

use Rector\DependencyInjection\Tests\Rector\Property\AnnotatedPropertyInjectToConstructorInjectionRector\Source\SomeProductWithInterface;

class ChildPropertyCallProtected extends SomeParentWithInject
{
    public function run()
    {
        return $this->someProductWith;
    }
}

class SomeParentWithInject
{
    /**
     * @inject
     * @var SomeProductWithInterface
     */
    public $someProductWith;
}

?>
-----
<?php

namespace Rector\DependencyInjection\Tests\Rector\Property\AnnotatedPropertyInjectToConstructorInjectionRector\Fixture;

use Rector\DependencyInjection\Tests\Rector\Property\AnnotatedPropertyInjectToConstructorInjectionRector\Source\SomeProductWithInterface;

class ChildPropertyCallProtected extends SomeParentWithInject
{
    public function run()
    {
        return $this->someProductWith;
    }
}

class SomeParentWithInject
{
    public function __construct(protected \Rector\DependencyInjection\Tests\Rector\Property\AnnotatedPropertyInjectToConstructorInjectionRector\Source\SomeProductWithInterface $someProductWith)
    {
    }
}

?>
