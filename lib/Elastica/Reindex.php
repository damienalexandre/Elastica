<?php

namespace Elastica;

use Elastica\Query\AbstractQuery;
use Elastica\Script\AbstractScript;
use Elastica\Script\Script;

class Reindex extends Param
{
    const VERSION_TYPE = 'version_type';
    const VERSION_TYPE_INTERNAL = 'internal';
    const VERSION_TYPE_EXTERNAL = 'external';
    const OPERATION_TYPE = 'op_type';
    const OPERATION_TYPE_CREATE = 'create';
    const CONFLICTS = 'conflicts';
    const CONFLICTS_PROCEED = 'proceed';
    const TYPE = 'type';
    const SIZE = 'size';
    const QUERY = 'query';
    const SORT = 'sort';
    const SCRIPT = 'script';
    const SOURCE = '_source';
    const REMOTE = 'remote';
    const SLICE = 'slice';
    const REFRESH = 'refresh';
    const WAIT_FOR_COMPLETION = 'wait_for_completion';
    const WAIT_FOR_COMPLETION_FALSE = 'false';
    const WAIT_FOR_ACTIVE_SHARDS = 'wait_for_active_shards';
    const TIMEOUT = 'timeout';
    const SCROLL = 'scroll';
    const REQUESTS_PER_SECOND = 'requests_per_second';

    /**
     * @var Index
     */
    protected $_oldIndex;

    /**
     * @var Index
     */
    protected $_newIndex;

    /**
     * @var array
     */
    protected $_options;

    /**
     * @var Response|null
     */
    protected $_lastResponse;

    public function __construct(Index $oldIndex, Index $newIndex, array $params = [])
    {
        $this->_oldIndex = $oldIndex;
        $this->_newIndex = $newIndex;

        $this->setParams($params);
    }

    public function run()
    {
        $body = $this->_getBody($this->_oldIndex, $this->_newIndex, $this->getParams());

        $reindexEndpoint = new \Elasticsearch\Endpoints\Reindex();
        $params = \array_intersect_key($this->getParams(), \array_fill_keys($reindexEndpoint->getParamWhitelist(), null));
        $reindexEndpoint->setParams($params);
        $reindexEndpoint->setBody($body);

        $this->_lastResponse = $this->_oldIndex->getClient()->requestEndpoint($reindexEndpoint);

        return $this->_lastResponse;
    }

    protected function _getBody($oldIndex, $newIndex, $params)
    {
        $body = \array_merge([
            'source' => $this->_getSourcePartBody($oldIndex, $params),
            'dest' => $this->_getDestPartBody($newIndex, $params),
        ], $this->_resolveBodyOptions($params));

        $body = $this->_setBodyScript($body);

        return $body;
    }

    protected function _getSourcePartBody(Index $index, array $params)
    {
        $sourceBody = \array_merge([
            'index' => $index->getName(),
        ], $this->_resolveSourceOptions($params));

        $sourceBody = $this->_setSourceQuery($sourceBody);
        $sourceBody = $this->_setSourceType($sourceBody);

        return $sourceBody;
    }

    protected function _getDestPartBody(Index $index, array $params)
    {
        $destBody = \array_merge([
            'index' => $index->getName(),
        ], $this->_resolveDestOptions($params));

        return $destBody;
    }

    private function _resolveSourceOptions(array $params)
    {
        return \array_intersect_key($params, [
            self::TYPE => null,
            self::QUERY => null,
            self::SORT => null,
            self::SOURCE => null,
            self::REMOTE => null,
            self::SLICE => null,
        ]);
    }

    private function _resolveDestOptions(array $params)
    {
        return \array_intersect_key($params, [
            self::VERSION_TYPE => null,
            self::OPERATION_TYPE => null,
        ]);
    }

    private function _resolveBodyOptions(array $params)
    {
        return \array_intersect_key($params, [
            self::SIZE => null,
            self::CONFLICTS => null,
        ]);
    }

    private function _setSourceQuery(array $sourceBody)
    {
        if (isset($sourceBody[self::QUERY]) && $sourceBody[self::QUERY] instanceof AbstractQuery) {
            $sourceBody[self::QUERY] = $sourceBody[self::QUERY]->toArray();
        }

        return $sourceBody;
    }

    private function _setSourceType(array $sourceBody)
    {
        if (isset($sourceBody[self::TYPE]) && !\is_array($sourceBody[self::TYPE])) {
            $sourceBody[self::TYPE] = [$sourceBody[self::TYPE]];
        }
        if (isset($sourceBody[self::TYPE])) {
            foreach ($sourceBody[self::TYPE] as $key => $type) {
                if ($type instanceof Type) {
                    $sourceBody[self::TYPE][$key] = $type->getName();
                }
            }
        }

        return $sourceBody;
    }

    private function _setBodyScript(array $body)
    {
        if (!$this->hasParam(self::SCRIPT)) {
            return $body;
        }

        $script = $this->getParam(self::SCRIPT);

        if ($script instanceof AbstractScript) {
            $body = \array_merge($body, $script->toArray());
        } else {
            $body[self::SCRIPT] = $script;
        }

        return $body;
    }

    public function setWaitForCompletion($value)
    {
        \is_bool($value) && $value = $value ? 'true' : 'false';

        $this->setParam(self::WAIT_FOR_COMPLETION, $value);
    }

    public function setWaitForActiveShards($value)
    {
        $this->setParam(self::WAIT_FOR_ACTIVE_SHARDS, $value);
    }

    public function setTimeout($value)
    {
        $this->setParam(self::TIMEOUT, $value);
    }

    public function setScroll($value)
    {
        $this->setParam(self::SCROLL, $value);
    }

    public function setRequestsPerSecond($value)
    {
        $this->setParam(self::REQUESTS_PER_SECOND, $value);
    }

    public function setScript(Script $script)
    {
        $this->setParam(self::SCRIPT, $script);
    }

    public function getTaskId()
    {
        $taskId = null;
        if ($this->_lastResponse instanceof Response) {
            $taskId = $this->_lastResponse->getData()['task'] ? $this->_lastResponse->getData()['task'] : null;
        }

        return $taskId;
    }
}
