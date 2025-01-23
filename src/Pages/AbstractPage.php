<?php

namespace Phntm\Lib\Pages;

use Phntm\Lib\Infra\Debug\Debugger;
use Bchubbweb\PhntmFramework\View\TemplateManager;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractPage implements PageInterface
{
    use Traits\Meta;

    private TemplateManager $twig;

    protected array $view_variables = [];

    protected bool $use_template = true;

    //protected bool $is_framework_page = false;

    /**
     * Template used for rendering whole document
     */
    protected string|false|null $render_template = null;

    /**
     * View used to render page content
     */
    protected string|false|null $render_view = null;

    private string $full_render_view;

    /**
     * AbstractPage constructor.
     *
     * @param array $dynamic_params
     */
    final public function __construct(protected array $dynamic_params = [])
    {
        try {
            if (!isset($this->render_template)) {
                $this->render_template = 'phntm/View/templates/Document.twig';
            }

            $this->twig = new TemplateManager($this->render_template);
        } catch (\Throwable $e) {
            dump($e);
            exit;
        }
    }

    abstract public function __invoke(Request $request): void;

    final public function render($request): StreamInterface
    {
        $this($request);

        $pageDirectory = dirname((new \ReflectionClass(static::class))->getFileName());

        if (!isset($this->render_view)) {

            // render_view located in the same directory as the page class
            $this->render_view = $pageDirectory . '/view.twig';
            $this->full_render_view = $this->render_view;

        } elseif (file_exists($pageDirectory . '/' . $this->render_view)) {

            // render_view is a relative path from the page class
            $this->full_render_view = $pageDirectory . '/' . $this->render_view;

        } elseif (file_exists(PAGES . $this->render_view)) {

            // render_view is a relative path from the PAGES directory
            $this->full_render_view = PAGES . $this->render_view;

        } elseif (file_exists(ROOT . $this->render_view)) {

            // render_view is a full path from the root of the project
            $this->full_render_view = ROOT . $this->render_view;

        } else {
            // no view file found
            $this->withContentType('text/html');
            return Stream::create('');
        }
        Debugger::startMeasure('page_render', 'Rendering');

        $this->twig->addView($this->full_render_view);

        $body = $this->twig->renderTemplate([
            ...$this->view_variables, 
            'phntm_meta' => $this->getMeta()
        ], $this->use_template);

        Debugger::stopMeasure('page_render');

        return Stream::create($body);
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->dynamic_params)) {
            return $this->dynamic_params[$name];
        }
        return null;
    }

    final public function renderWith(array $variables): void
    {
        $this->view_variables = array_merge($this->view_variables, $variables);
    }
}
