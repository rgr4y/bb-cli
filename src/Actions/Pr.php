<?php

namespace BBCli\BBCli\Actions;

use BBCli\BBCli\Base;

/**
 * Pull Request
 * All commands for pull request.
 *
 * @see https://bb-cli.github.io/docs/commands/pull-request
 */
class Pr extends Base
{
    /**
     * Pull request default command.
     */
    const DEFAULT_METHOD = 'list';

    /**
     * Pull request commands.
     */
    const AVAILABLE_COMMANDS = [
        'list'             => 'list, l',
        'view'             => 'view, show',
        'diff'             => 'diff, d',
        'files'            => 'files',
        'comments'         => 'comments',
        'commits'          => 'commits, checks, c',
        'approve'          => 'approve, a',
        'unApprove'        => 'no-approve, na',
        'requestChanges'   => 'request-changes, rc',
        'unRequestChanges' => 'no-request-changes, nrc',
        'decline'          => 'decline, close',
        'merge'            => 'merge, m',
        'create'           => 'create',
    ];

    /**
     * List pull request for repository.
     *
     * @param string $destination
     * @return void
     */
    public function list($destination = '')
    {
        $prs = [];

        foreach ($this->makeRequest('GET', '/pullrequests?state=OPEN')['values'] as $prInfo) {
            if (!empty($destination) &&
                array_get($prInfo, 'destination.branch.name') !== $destination
            ) {
                continue;
            }

            $prDetail = $this->makeRequest('GET', "/pullrequests/{$prInfo['id']}");

            $prs[] = $this->buildPrData($prInfo, $prDetail);
        }

        if (empty($prs)) {
            o('No open pull requests.', 'gray');
            return;
        }

        // JSON mode: hand off the full array and let the shutdown handler emit it
        if (!empty($GLOBALS['BB_JSON_MODE'])) {
            o($prs, 'yellow');
            return;
        }

        $first = true;
        foreach ($prs as $pr) {
            if (!$first) {
                echo "\033[2m\033[38;5;244m  " . str_repeat('·', 60) . "\033[0m\n";
            }
            $first = false;
            $this->printPr($pr);
        }
    }

    /**
     * View pull request detail.
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function view($prNumber)
    {
        $pr = $this->makeRequest('GET', "/pullrequests/{$prNumber}");
        $data = $this->buildPrData($pr, $pr);

        if (!empty($GLOBALS['BB_JSON_MODE'])) {
            o($data, 'yellow');
            return;
        }

        $this->printPr($data);
    }

    /**
     * Get pull request diff.
     *
     * @param int $prNumber
     * @return void
     */
    public function diff($prNumber)
    {
        o($this->makeRequest('GET', "/pullrequests/{$prNumber}/diff"), 'yellow');
    }

    /**
     * Diff stats file.
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function files($prNumber)
    {
        $response = array_get($this->makeRequest('GET', "/pullrequests/{$prNumber}/diffstat"), 'values');

        foreach ($response as $row) {
            o(array_get($row, 'new.path'), 'yellow');
        }
    }

    /**
     * Get pull request commits.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function commits($prNumber)
    {
        $result = [];

        foreach ($this->makeRequest('GET', "/pullrequests/{$prNumber}/commits")['values'] as $prInfo) {
            $result[] = trim(str_replace('\n', PHP_EOL, array_get($prInfo, 'summary.raw')));
        }

        o($result, 'yellow');
    }

    /**
     * Get pull request comments.
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function comments($prNumber)
    {
        $response = $this->makeRequest('GET', "/pullrequests/{$prNumber}/comments");
        $result = [];

        foreach ($response['values'] ?? [] as $comment) {
            $entry = [
                'id'      => $comment['id'],
                'author'  => array_get($comment, 'author.display_name'),
                'created' => substr(array_get($comment, 'created_on', ''), 0, 10),
                'content' => array_get($comment, 'content.raw'),
            ];
            if (!empty($comment['inline'])) {
                $entry['file'] = array_get($comment, 'inline.path');
                $line = array_get($comment, 'inline.to');
                if ($line !== null) {
                    $entry['line'] = $line;
                }
            }
            $result[] = $entry;
        }

        if (empty($result)) {
            o('No comments.', 'gray');
            return;
        }

        if (!empty($GLOBALS['BB_JSON_MODE'])) {
            o($result, 'yellow');
            return;
        }

        $cyan   = "\033[0;36m";
        $yellow = "\033[0;33m";
        $dim    = "\033[2m\033[38;5;244m";
        $reset  = "\033[0m";
        $first  = true;

        foreach ($result as $c) {
            if (!$first) {
                echo $dim . "  " . str_repeat('·', 60) . $reset . "\n";
            }
            $first = false;

            echo $cyan . "#{$c['id']}" . $reset . "  " . $yellow . $c['author'] . $reset . "  " . $dim . $c['created'] . $reset . "\n";
            if (!empty($c['file'])) {
                echo $dim . "  {$c['file']}" . (isset($c['line']) ? ":{$c['line']}" : '') . $reset . "\n";
            }
            echo $c['content'] . "\n";
        }
    }

    /**
     * Approve pull request.
     *
     * @param array $prNumbers
     * @return void
     *
     * @throws \Exception
     */
    public function approve(...$prNumbers)
    {
        if (empty($prNumbers)) {
            throw new \Exception('Pr number required.', 1);
        }

        // if first param is zero than approve all
        if ($prNumbers[0] == 0) {
            $prNumbers = [];

            foreach ($this->makeRequest('GET', '/pullrequests?state=OPEN')['values'] as $prInfo) {
                $prNumbers[] = $prInfo['id'];
            }

            if (empty($prNumbers)) {
                throw new \Exception('Pr not found.', 1);
            }
        }

        foreach ($prNumbers as $prNumber) {
            $this->makeRequest('POST', "/pullrequests/{$prNumber}/approve");
            o("{$prNumber} Approved.", 'green');
        }
    }

    /**
     * Revert pull request to not approved status.
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function unApprove($prNumber)
    {
        o($this->makeRequest('DELETE', "/pullrequests/{$prNumber}/approve"));
    }

    /**
     *  Request changes for pull request
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function requestChanges($prNumber)
    {
        o($this->makeRequest('POST', "/pullrequests/{$prNumber}/request-changes"));
    }

    /**
     * Revert pull request to not request changes status.
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function unRequestChanges($prNumber)
    {
        o($this->makeRequest('DELETE', "/pullrequests/{$prNumber}/request-changes"));
    }

    /**
     * Decline pull request.
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function decline($prNumber)
    {
        $this->makeRequest('POST', "/pullrequests/{$prNumber}/decline");
        o('OK.', 'green');
    }

    /**
     * Merge pull request.
     *
     * @param int $prNumber
     * @return void
     *
     * @throws \Exception
     */
    public function merge($prNumber)
    {
        o($this->makeRequest('POST', "/pullrequests/{$prNumber}/merge")['state'], 'green');
    }

    /**
     * Create pull request from "x" to test "y".
     *
     * @param string $fromBranch
     * @param string $toBranch
     * @param int $addDefaultReviewers
     * @return void
     *
     * @throws \Exception
     */
    public function create($fromBranch, $toBranch = '', $addDefaultReviewers = 1)
    {
        if (empty($toBranch)) {
            $toBranch = $fromBranch;
            $fromBranch = trim(exec('git symbolic-ref --short HEAD'));
        }

        $this->bulkCreate(
            explode(',', $toBranch),
            $fromBranch,
            $addDefaultReviewers == 1
        );
    }

    /**
     * Create pull request from "x" to test "y".
     *
     * @param array $toBranches
     * @param string $fromBranch
     * @param bool $addDefaultReviewers
     * @return void
     *
     * @throws \Exception
     */
    private function bulkCreate($toBranches, $fromBranch, $addDefaultReviewers = true)
    {
        $responses = [];

        $defaultReviewers = $addDefaultReviewers ? $this->defaultReviewers() : [];

        foreach ($toBranches as $toBranch) {
            $response = $this->makeRequest('POST', '/pullrequests', [
                'title' => "Merge {$fromBranch} into {$toBranch}",
                'source' => [
                    'branch' => [
                        'name' => $fromBranch,
                    ],
                ],
                'destination' => [
                    'branch' => [
                        'name' => $toBranch,
                    ],
                ],
                'reviewers' => $defaultReviewers,
            ]);

            $responses[] = [
                'id' => array_get($response, 'id'),
                'link' => array_get($response, 'links.html.href'),
            ];
        }

        o([
            'pullRequests' => $responses,
        ]);
    }

    /**
     * Build a normalized PR data array from API response(s).
     * $prInfo = list item, $prDetail = full PR object (same for view).
     */
    private function buildPrData(array $prInfo, array $prDetail): array
    {
        $reviewers = implode(', ', array_map(
            fn($r) => $r['display_name'],
            $prDetail['reviewers'] ?? []
        ));

        $participants = implode(' | ', array_filter(array_map(function ($p) {
            return !empty($p['state']) ? sprintf('%s → %s', $p['user']['display_name'], $p['state']) : null;
        }, $prDetail['participants'] ?? []), fn($v) => $v !== null));

        $data = [
            'id'          => $prInfo['id'],
            'author'      => array_get($prInfo, 'author.nickname'),
            'source'      => array_get($prInfo, 'source.branch.name'),
            'destination' => array_get($prInfo, 'destination.branch.name'),
            'link'        => array_get($prInfo, 'links.html.href'),
        ];

        if (!empty($prDetail['title'])) {
            // array_merge intentionally used here (not +): we want title/state inserted after id with defined key order.
            // The duplicate 'id' key in the left array is harmless — array_merge keeps the last occurrence.
            $data = array_merge(['id' => $data['id'], 'title' => $prDetail['title'], 'state' => $prDetail['state']], $data);
        }

        if ($reviewers)    $data['reviewers']    = $reviewers;
        if ($participants) $data['participants'] = $participants;

        return $data;
    }

    /**
     * Print a single PR row with aligned key/value formatting.
     */
    private function printPr(array $pr): void
    {
        $cyan   = "\033[0;36m";
        $yellow = "\033[0;33m";
        $reset  = "\033[0m";

        foreach ($pr as $key => $value) {
            echo $cyan . ucfirst($key) . ':' . $reset . ' ' . $yellow . $value . $reset . "\n";
        }
    }

    /**
     * Get default reviewers for repository.
     *
     * @return array
     *
     * @throws \Exception
     */
    private function defaultReviewers()
    {
        $currentUserUuid = $this->currentUserUuid();
        $response = $this->makeRequest('GET', '/default-reviewers');

        // remove current user from reviewers
        return array_values(array_filter($response['values'] ?? [], function ($reviewer) use ($currentUserUuid) {
            return $reviewer['uuid'] !== $currentUserUuid;
        }));
    }

    /**
     * Get current user uuid.
     *
     * @return string
     *
     * @throws \Exception
     */
    private function currentUserUuid()
    {
        $response = $this->makeRequest(
            'GET',
            '/user',
            [],
            false
        );

        return array_get($response, 'uuid');
    }
}
