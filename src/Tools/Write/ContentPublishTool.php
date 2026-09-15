<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Write;

use EvolutionCMS\DocumentManager\Facades\DocumentManager;
use EvolutionCMS\Exceptions\ServiceActionException;
use EvolutionCMS\Exceptions\ServiceValidationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.write.content.publish')]
#[Description('Publish, unpublish, delete (to trash) or restore a document. Requires publish_document or delete_document.')]
class ContentPublishTool extends BaseWriteTool
{
    /** @var array<string, array{permission: string, action: int, label: string}> */
    private const ACTIONS = [
        'publish' => ['permission' => 'publish_document', 'action' => 61, 'label' => 'Published resource via MCP'],
        'unpublish' => ['permission' => 'publish_document', 'action' => 62, 'label' => 'Unpublished resource via MCP'],
        'delete' => ['permission' => 'delete_document', 'action' => 6, 'label' => 'Deleted resource via MCP'],
        'undelete' => ['permission' => 'delete_document', 'action' => 63, 'label' => 'Restored resource via MCP'],
    ];

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'string', 'in:publish,unpublish,delete,undelete'],
        ]);

        $id = (int)$validated['id'];
        $action = (string)$validated['action'];
        $spec = self::ACTIONS[$action];

        $this->requirePermission($spec['permission']);
        $document = $this->findDocument($id);
        $this->requireDocumentAccess($id);
        $this->requireUnlocked(7, $id);

        if (in_array($action, ['delete', 'unpublish'], true) && $id === (int)evo()->getConfig('site_start')) {
            throw ValidationException::withMessages(['id' => 'The site start document cannot be unpublished or deleted.']);
        }

        try {
            DocumentManager::{$action}(['id' => $id]);
        } catch (ServiceValidationException $e) {
            throw ValidationException::withMessages(['document' => json_encode($e->getValidationErrors())]);
        } catch (ServiceActionException $e) {
            throw ValidationException::withMessages(['document' => $e->getMessage()]);
        }

        $fresh = $this->findDocument($id);
        $this->logManagerAction($spec['action'], $spec['label'], $id, (string)$document->pagetitle);

        return $this->respondItem([
            'id' => $id,
            'action' => $action,
            'published' => (bool)$fresh->published,
            'deleted' => (bool)$fresh->deleted,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(1)->required(),
            'action' => $schema->string()->enum(array_keys(self::ACTIONS))->required(),
        ];
    }
}
