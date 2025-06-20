<?php

namespace GestaoInterativa\Tests\View;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\View\View;

class ViewTest extends TestCase
{
    private $view;
    private $viewPath;

    protected function setUp(): void
    {
        $this->viewPath = __DIR__ . '/../../views';
        $this->view = new View($this->viewPath);
    }

    public function testViewCanBeCreated()
    {
        $this->assertInstanceOf(View::class, $this->view);
    }

    public function testRender()
    {
        $content = $this->view->render('test', ['name' => 'Test']);
        $this->assertStringContainsString('Hello Test', $content);
    }

    public function testRenderWithLayout()
    {
        $content = $this->view->render('test', ['name' => 'Test'], 'layout');
        $this->assertStringContainsString('Hello Test', $content);
        $this->assertStringContainsString('Layout Content', $content);
    }

    public function testRenderWithSections()
    {
        $content = $this->view->render('test-with-sections', ['name' => 'Test'], 'layout-with-sections');
        $this->assertStringContainsString('Section Content', $content);
        $this->assertStringContainsString('Layout with Sections', $content);
    }

    public function testRenderWithNestedSections()
    {
        $content = $this->view->render('test-with-nested-sections', ['name' => 'Test'], 'layout-with-nested-sections');
        $this->assertStringContainsString('Nested Section Content', $content);
        $this->assertStringContainsString('Layout with Nested Sections', $content);
    }

    public function testRenderWithIncludes()
    {
        $content = $this->view->render('test-with-includes', ['name' => 'Test']);
        $this->assertStringContainsString('Included Content', $content);
    }

    public function testRenderWithNestedIncludes()
    {
        $content = $this->view->render('test-with-nested-includes', ['name' => 'Test']);
        $this->assertStringContainsString('Nested Included Content', $content);
    }

    public function testRenderWithEscapedContent()
    {
        $content = $this->view->render('test-with-escaped-content', ['name' => '<script>alert("Test")</script>']);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;Test&quot;)&lt;/script&gt;', $content);
    }

    public function testRenderWithRawContent()
    {
        $content = $this->view->render('test-with-raw-content', ['name' => '<script>alert("Test")</script>']);
        $this->assertStringContainsString('<script>alert("Test")</script>', $content);
    }

    public function testRenderWithConditionals()
    {
        $content = $this->view->render('test-with-conditionals', ['show' => true]);
        $this->assertStringContainsString('This is shown', $content);
        $this->assertStringNotContainsString('This is not shown', $content);

        $content = $this->view->render('test-with-conditionals', ['show' => false]);
        $this->assertStringNotContainsString('This is shown', $content);
        $this->assertStringContainsString('This is not shown', $content);
    }

    public function testRenderWithLoops()
    {
        $content = $this->view->render('test-with-loops', ['items' => ['Item 1', 'Item 2', 'Item 3']]);
        $this->assertStringContainsString('Item 1', $content);
        $this->assertStringContainsString('Item 2', $content);
        $this->assertStringContainsString('Item 3', $content);
    }

    public function testRenderWithNestedLoops()
    {
        $content = $this->view->render('test-with-nested-loops', [
            'items' => [
                ['name' => 'Item 1', 'subitems' => ['Subitem 1', 'Subitem 2']],
                ['name' => 'Item 2', 'subitems' => ['Subitem 3', 'Subitem 4']]
            ]
        ]);
        $this->assertStringContainsString('Item 1', $content);
        $this->assertStringContainsString('Subitem 1', $content);
        $this->assertStringContainsString('Subitem 2', $content);
        $this->assertStringContainsString('Item 2', $content);
        $this->assertStringContainsString('Subitem 3', $content);
        $this->assertStringContainsString('Subitem 4', $content);
    }

    public function testRenderWithFilters()
    {
        $content = $this->view->render('test-with-filters', ['name' => 'test']);
        $this->assertStringContainsString('TEST', $content);
        $this->assertStringContainsString('Test', $content);
    }

    public function testRenderWithCustomFilters()
    {
        $this->view->addFilter('custom', function($value) {
            return "Custom: {$value}";
        });

        $content = $this->view->render('test-with-custom-filters', ['name' => 'test']);
        $this->assertStringContainsString('Custom: test', $content);
    }

    public function testRenderWithHelpers()
    {
        $content = $this->view->render('test-with-helpers', ['name' => 'test']);
        $this->assertStringContainsString('Hello test', $content);
        $this->assertStringContainsString('Goodbye test', $content);
    }

    public function testRenderWithCustomHelpers()
    {
        $this->view->addHelper('custom', function($name) {
            return "Custom: {$name}";
        });

        $content = $this->view->render('test-with-custom-helpers', ['name' => 'test']);
        $this->assertStringContainsString('Custom: test', $content);
    }

    public function testRenderWithSharedData()
    {
        $this->view->share('shared', 'Shared Data');
        $content = $this->view->render('test-with-shared-data', ['name' => 'test']);
        $this->assertStringContainsString('Shared Data', $content);
    }

    public function testRenderWithComposers()
    {
        $this->view->composer('test-with-composers', function($view) {
            $view->with('composed', 'Composed Data');
        });

        $content = $this->view->render('test-with-composers', ['name' => 'test']);
        $this->assertStringContainsString('Composed Data', $content);
    }

    public function testRenderWithNestedComposers()
    {
        $this->view->composer('test-with-nested-composers', function($view) {
            $view->with('composed', 'Composed Data');
        });

        $content = $this->view->render('test-with-nested-composers', ['name' => 'test']);
        $this->assertStringContainsString('Composed Data', $content);
    }

    public function testRenderWithViewComposers()
    {
        $this->view->composer(['test1', 'test2'], function($view) {
            $view->with('composed', 'Composed Data');
        });

        $content = $this->view->render('test1', ['name' => 'test']);
        $this->assertStringContainsString('Composed Data', $content);

        $content = $this->view->render('test2', ['name' => 'test']);
        $this->assertStringContainsString('Composed Data', $content);
    }

    public function testRenderWithWildcardComposers()
    {
        $this->view->composer('test*', function($view) {
            $view->with('composed', 'Composed Data');
        });

        $content = $this->view->render('test1', ['name' => 'test']);
        $this->assertStringContainsString('Composed Data', $content);

        $content = $this->view->render('test2', ['name' => 'test']);
        $this->assertStringContainsString('Composed Data', $content);
    }

    public function testRenderWithCreatorComposers()
    {
        $this->view->creator('test-with-creator-composers', function($view) {
            $view->with('created', 'Created Data');
        });

        $content = $this->view->render('test-with-creator-composers', ['name' => 'test']);
        $this->assertStringContainsString('Created Data', $content);
    }

    public function testRenderWithNestedCreatorComposers()
    {
        $this->view->creator('test-with-nested-creator-composers', function($view) {
            $view->with('created', 'Created Data');
        });

        $content = $this->view->render('test-with-nested-creator-composers', ['name' => 'test']);
        $this->assertStringContainsString('Created Data', $content);
    }

    public function testRenderWithViewCreators()
    {
        $this->view->creator(['test1', 'test2'], function($view) {
            $view->with('created', 'Created Data');
        });

        $content = $this->view->render('test1', ['name' => 'test']);
        $this->assertStringContainsString('Created Data', $content);

        $content = $this->view->render('test2', ['name' => 'test']);
        $this->assertStringContainsString('Created Data', $content);
    }

    public function testRenderWithWildcardCreators()
    {
        $this->view->creator('test*', function($view) {
            $view->with('created', 'Created Data');
        });

        $content = $this->view->render('test1', ['name' => 'test']);
        $this->assertStringContainsString('Created Data', $content);

        $content = $this->view->render('test2', ['name' => 'test']);
        $this->assertStringContainsString('Created Data', $content);
    }

    public function testRenderWithExtensions()
    {
        $this->view->addExtension('custom', function($value) {
            return "Custom: {$value}";
        });

        $content = $this->view->render('test-with-extensions', ['name' => 'test']);
        $this->assertStringContainsString('Custom: test', $content);
    }

    public function testRenderWithNestedExtensions()
    {
        $this->view->addExtension('custom', function($value) {
            return "Custom: {$value}";
        });

        $content = $this->view->render('test-with-nested-extensions', ['name' => 'test']);
        $this->assertStringContainsString('Custom: test', $content);
    }

    public function testRenderWithViewExtensions()
    {
        $this->view->addExtension(['test1', 'test2'], function($value) {
            return "Custom: {$value}";
        });

        $content = $this->view->render('test1', ['name' => 'test']);
        $this->assertStringContainsString('Custom: test', $content);

        $content = $this->view->render('test2', ['name' => 'test']);
        $this->assertStringContainsString('Custom: test', $content);
    }

    public function testRenderWithWildcardExtensions()
    {
        $this->view->addExtension('test*', function($value) {
            return "Custom: {$value}";
        });

        $content = $this->view->render('test1', ['name' => 'test']);
        $this->assertStringContainsString('Custom: test', $content);

        $content = $this->view->render('test2', ['name' => 'test']);
        $this->assertStringContainsString('Custom: test', $content);
    }

    public function testRenderWithViewData()
    {
        $content = $this->view->render('test-with-view-data', ['name' => 'test']);
        $this->assertStringContainsString('test', $content);
    }

    public function testRenderWithNestedViewData()
    {
        $content = $this->view->render('test-with-nested-view-data', [
            'user' => [
                'name' => 'test',
                'email' => 'test@example.com'
            ]
        ]);
        $this->assertStringContainsString('test', $content);
        $this->assertStringContainsString('test@example.com', $content);
    }

    public function testRenderWithViewDataAndLayout()
    {
        $content = $this->view->render('test-with-view-data', ['name' => 'test'], 'layout');
        $this->assertStringContainsString('test', $content);
        $this->assertStringContainsString('Layout Content', $content);
    }

    public function testRenderWithViewDataAndSections()
    {
        $content = $this->view->render('test-with-view-data-and-sections', ['name' => 'test'], 'layout-with-sections');
        $this->assertStringContainsString('test', $content);
        $this->assertStringContainsString('Section Content', $content);
        $this->assertStringContainsString('Layout with Sections', $content);
    }

    public function testRenderWithViewDataAndIncludes()
    {
        $content = $this->view->render('test-with-view-data-and-includes', ['name' => 'test']);
        $this->assertStringContainsString('test', $content);
        $this->assertStringContainsString('Included Content', $content);
    }

    public function testRenderWithViewDataAndConditionals()
    {
        $content = $this->view->render('test-with-view-data-and-conditionals', ['show' => true]);
        $this->assertStringContainsString('This is shown', $content);
        $this->assertStringNotContainsString('This is not shown', $content);

        $content = $this->view->render('test-with-view-data-and-conditionals', ['show' => false]);
        $this->assertStringNotContainsString('This is shown', $content);
        $this->assertStringContainsString('This is not shown', $content);
    }

    public function testRenderWithViewDataAndLoops()
    {
        $content = $this->view->render('test-with-view-data-and-loops', ['items' => ['Item 1', 'Item 2', 'Item 3']]);
        $this->assertStringContainsString('Item 1', $content);
        $this->assertStringContainsString('Item 2', $content);
        $this->assertStringContainsString('Item 3', $content);
    }

    public function testRenderWithViewDataAndFilters()
    {
        $content = $this->view->render('test-with-view-data-and-filters', ['name' => 'test']);
        $this->assertStringContainsString('TEST', $content);
        $this->assertStringContainsString('Test', $content);
    }

    public function testRenderWithViewDataAndHelpers()
    {
        $content = $this->view->render('test-with-view-data-and-helpers', ['name' => 'test']);
        $this->assertStringContainsString('Hello test', $content);
        $this->assertStringContainsString('Goodbye test', $content);
    }

    public function testRenderWithViewDataAndSharedData()
    {
        $this->view->share('shared', 'Shared Data');
        $content = $this->view->render('test-with-view-data-and-shared-data', ['name' => 'test']);
        $this->assertStringContainsString('test', $content);
        $this->assertStringContainsString('Shared Data', $content);
    }

    public function testRenderWithViewDataAndComposers()
    {
        $this->view->composer('test-with-view-data-and-composers', function($view) {
            $view->with('composed', 'Composed Data');
        });

        $content = $this->view->render('test-with-view-data-and-composers', ['name' => 'test']);
        $this->assertStringContainsString('test', $content);
        $this->assertStringContainsString('Composed Data', $content);
    }

    public function testRenderWithViewDataAndExtensions()
    {
        $this->view->addExtension('custom', function($value) {
            return "Custom: {$value}";
        });

        $content = $this->view->render('test-with-view-data-and-extensions', ['name' => 'test']);
        $this->assertStringContainsString('test', $content);
        $this->assertStringContainsString('Custom: test', $content);
    }
} 