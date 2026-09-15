<?php

declare(strict_types=1);

namespace EvolutionCMS\eMCP\Tools\Write;

use EvolutionCMS\DocumentManager\Facades\DocumentManager;
use EvolutionCMS\Exceptions\ServiceActionException;
use EvolutionCMS\Exceptions\ServiceValidationException;
use EvolutionCMS\Models\SiteContent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('evo.write.content.update')]
#[Description('Update fields and TVs of an existing document. Requires save_document; publish state changes need publish_document.')]
class ContentUpdateTool extends BaseWriteTool
{
    /** @var array<int, string> */
    public const WRITABLE_FIELDS = [
        'pagetitle', 'longtitle', 'description', 'alias', 'introtext', 'content', 'menutitle',
        'template', 'parent', 'published', 'pub_date', 'unpub_date', 'hidemenu', 'menuindex',
        'isfolder', 'searchable', 'cacheable', 'richtext', 'type', 'contentType', 'content_dispo',
        'link_attributes', 'alias_visible', 'hide_from_tree', 'show_in_search',
    ];

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'fields' => ['nullable', 'array'],
            'tvs' => ['nullable', 'array'],
        ]);

        $id = (int)$validated['id'];
        $fields = $this->pickFields((array)($validated['fields'] ?? []), self::WRITABLE_FIELDS);
        $tvs = (array)($validated['tvs'] ?? []);

        if ($fields === [] && $tvs === []) {
            throw ValidationException::withMessages(['fields' => 'Nothing to update: pass fields and/or tvs.']);
        }

        $this->requirePermission('save_document');
        $document = $this->findDocument($id);
        $this->requireDocumentAccess($id);
        $this->requireUnlocked(7, $id);

        if (isset($fields['parent']) && (int)$fields['parent'] !== (int)$document->parent) {
            $this->requireDocumentAccess(0, (int)$fields['parent']);
        }

        // DocumentEdit works on a full record (it reads published/pub_date/parent unconditionally),
        // so a partial update is completed from the stored row.
        $current = [];
        foreach (['pagetitle', 'alias', 'template', 'parent', 'published', 'pub_date', 'unpub_date', 'isfolder', 'hidemenu', 'menuindex'] as $column) {
            $current[$column] = $document->getAttribute($column);
        }

        $data = ['id' => $id] + $fields + $current + $this->tvValues($tvs);

        try {
            DocumentManager::edit($data);
        } catch (ServiceValidationException $e) {
            throw ValidationException::withMessages($this->flatten($e->getValidationErrors()));
        } catch (ServiceActionException $e) {
            throw ValidationException::withMessages(['document' => $e->getMessage()]);
        }

        $fresh = $this->findDocument($id);
        $this->logManagerAction(5, 'Saved resource via MCP', $id, (string)$fresh->pagetitle);

        return $this->respondItem([
            'id' => $id,
            'pagetitle' => $fresh->pagetitle,
            'alias' => $fresh->alias,
            'published' => (bool)$fresh->published,
            'editedon' => $fresh->editedon,
            'updated_fields' => array_keys($fields),
            'updated_tvs' => array_keys($tvs),
        ]);
    }

    /**
     * DocumentManager reads TV values from the data array by TV name.
     *
     * @param  array<string, mixed>  $tvs
     * @return array<string, mixed>
     */
    private function tvValues(array $tvs): array
    {
        $values = [];
        foreach ($tvs as $name => $value) {
            $name = trim((string)$name);
            if (!preg_match('~^[A-Za-z0-9_\-]+$~', $name)) {
                throw ValidationException::withMessages(['tvs' => "Invalid TV name [{$name}]."]);
            }
            if (in_array($name, self::WRITABLE_FIELDS, true) || $name === 'id') {
                throw ValidationException::withMessages(['tvs' => "TV name [{$name}] collides with a document field."]);
            }
            $values[$name] = is_array($value) ? implode('||', array_map('strval', $value)) : (string)$value;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $errors
     * @return array<string, string>
     */
    private function flatten(array $errors): array
    {
        $flat = [];
        foreach ($errors as $field => $messages) {
            $flat[(string)$field] = implode(' ', array_map('strval', (array)$messages));
        }

        return $flat;
    }

    /**
     * @return array<string, \Illuminate\Contracts\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->min(1)->required(),
            'fields' => $schema->object()->description('Document columns to change: ' . implode(', ', self::WRITABLE_FIELDS)),
            'tvs' => $schema->object()->description('Template variable values keyed by TV name'),
        ];
    }
}
