<?php
declare(strict_types=1);

/**
 * Order types of the SMM API v2 (as documented by MoreThanPanel).
 *
 * qty:  'input'     customer enters a quantity
 *       'comments'  quantity = number of comment lines
 *       'usernames' quantity = number of username lines
 *       'none'      fixed package, charged the service rate once
 * fields: extra parameters sent to the provider (list = one item per line)
 *
 * "Subscriptions" is not supported: the provider charges per future post, so it can't be prepaid.
 */
const ORDER_TYPES = [
    'Default' => ['qty' => 'input', 'fields' => []],
    'Package' => ['qty' => 'none', 'fields' => []],
    'SEO' => ['qty' => 'input', 'fields' => ['keywords' => 'list']],
    'Custom Comments' => ['qty' => 'comments', 'fields' => ['comments' => 'list']],
    'Custom Comments Package' => ['qty' => 'none', 'fields' => ['comments' => 'list']],
    'Mentions' => ['qty' => 'input', 'fields' => ['usernames' => 'list']],
    'Mentions with Hashtags' => ['qty' => 'input', 'fields' => ['usernames' => 'list', 'hashtags' => 'list']],
    'Mentions Custom List' => ['qty' => 'usernames', 'fields' => ['usernames' => 'list']],
    'Mentions Hashtag' => ['qty' => 'input', 'fields' => ['hashtag' => 'text']],
    'Comment Likes' => ['qty' => 'input', 'fields' => ['username' => 'text']],
    'Comment Replies' => ['qty' => 'comments', 'fields' => ['username' => 'text', 'comments' => 'list']],
    'Poll' => ['qty' => 'input', 'fields' => ['answer_number' => 'number']],
    'Invites from Groups' => ['qty' => 'input', 'fields' => ['groups' => 'list']],
];

const ORDER_FIELD_LABELS = [
    'keywords' => ['Keywords', 'One keyword per line'],
    'comments' => ['Comments', 'One comment per line'],
    'usernames' => ['Usernames', 'One username per line'],
    'hashtags' => ['Hashtags', 'One hashtag per line'],
    'hashtag' => ['Hashtag', 'Hashtag to take usernames from'],
    'username' => ['Username', 'Username of the comment owner'],
    'answer_number' => ['Answer number', 'Number of the poll answer to vote for'],
    'groups' => ['Groups', 'One group per line'],
];

function order_type(string $type): ?array
{
    foreach (ORDER_TYPES as $name => $spec) {
        if (strcasecmp($name, trim($type)) === 0) {
            return ['name' => $name] + $spec;
        }
    }
    return null;
}

/** Split a textarea into clean non-empty lines. */
function order_lines(string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text)), fn($l) => $l !== ''));
}

/**
 * Validate customer input for a service and build the provider parameters.
 *
 * @return array{ok: bool, error?: string, quantity?: int, units?: int, charge?: float, params?: array, runs?: ?int, interval?: ?int, extra?: array}
 */
function build_order(array $service, array $in): array
{
    $type = order_type((string)$service['type']);
    if (!$type) {
        return ['ok' => false, 'error' => 'This service type is not supported'];
    }
    $str = fn(string $k) => is_scalar($in[$k] ?? null) ? trim((string)$in[$k]) : '';

    $link = $str('link');
    if ($link === '' || mb_strlen($link) > 500 || preg_match('/\s/', $link)) {
        return ['ok' => false, 'error' => 'Enter a valid link'];
    }
    $params = ['link' => $link];
    $extra = [];

    foreach ($type['fields'] as $field => $kind) {
        [$label] = ORDER_FIELD_LABELS[$field];
        $value = $str($field);
        if ($kind === 'list') {
            $lines = order_lines($value);
            if (!$lines) {
                return ['ok' => false, 'error' => "$label: enter at least one line"];
            }
            if (count($lines) > 10000 || mb_strlen($value) > 60000) {
                return ['ok' => false, 'error' => "$label: too long"];
            }
            $params[$field] = implode("\n", $lines);
            $extra[$field] = count($lines);
        } elseif ($kind === 'number') {
            if (!ctype_digit($value) || (int)$value < 1) {
                return ['ok' => false, 'error' => "$label must be a positive number"];
            }
            $params[$field] = (int)$value;
            $extra[$field] = (int)$value;
        } else {
            if ($value === '' || mb_strlen($value) > 255 || preg_match('/\s/', $value)) {
                return ['ok' => false, 'error' => "Enter a valid $label"];
            }
            $params[$field] = $value;
            $extra[$field] = $value;
        }
    }

    $quantity = match ($type['qty']) {
        'input' => (int)$str('quantity'),
        'comments' => count(order_lines($params['comments'])),
        'usernames' => count(order_lines($params['usernames'])),
        default => 1,
    };
    if ($type['qty'] !== 'none'
        && ($quantity < (int)$service['min_quantity'] || $quantity > (int)$service['max_quantity'])) {
        $what = $type['qty'] === 'input' ? 'Quantity' : 'Number of ' . $type['qty'];
        return ['ok' => false, 'error' => "$what must be between {$service['min_quantity']} and {$service['max_quantity']}"];
    }
    if ($type['qty'] === 'input') {
        $params['quantity'] = $quantity;
    }

    // Drip-feed: deliver `quantity` every `interval` minutes, `runs` times
    $runs = $interval = null;
    if ($type['name'] === 'Default' && $service['dripfeed'] && $str('runs') !== '' && (int)$str('runs') > 1) {
        $runs = (int)$str('runs');
        $interval = (int)$str('interval');
        if ($runs > 1000 || $interval < 1 || $interval > 10080) {
            return ['ok' => false, 'error' => 'Drip-feed: runs must be 2-1000 and interval 1-10080 minutes'];
        }
        $params['runs'] = $runs;
        $params['interval'] = $interval;
    }

    $units = $quantity * ($runs ?? 1);
    $charge = $type['qty'] === 'none'
        ? round((float)$service['rate'], 4)
        : round((float)$service['rate'] * $units / 1000, 4);
    if ($charge <= 0) {
        return ['ok' => false, 'error' => 'This service has no price set'];
    }

    return ['ok' => true, 'quantity' => $quantity, 'units' => $units, 'charge' => $charge, 'params' => $params,
        'runs' => $runs, 'interval' => $interval, 'extra' => $extra];
}
