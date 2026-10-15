<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolAttach.php --- ver 5.0.0 --- 2026.10.07
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.
// 20261007 CDX/LH Restore the shared tenant SQL modification trail after writes.

require_once __DIR__ . '/poolPaths.php';
require_once __DIR__ . '/../../restapi/core/MobileSchema.php';

/** @return array{dir:string,path:string,filepath:string} Legacy finance folder and document filepath. */
function pool_attach_target(string $docRoot, int $kladdeId, int $lineId, string $fileName): array
{
    if ($kladdeId <= 0 || $lineId <= 0 || $fileName === '' || basename($fileName) !== $fileName || str_contains($fileName, '\\')) {
        throw new InvalidArgumentException('Invalid attachment target');
    }
    $filepath='/finance/'.$kladdeId.'/'.$lineId;
    $dir=rtrim($docRoot,'/').$filepath;
    return ['dir'=>$dir,'path'=>$dir.'/'.$fileName,'filepath'=>$filepath];
}

/** @return list<array<string,mixed>> Checked writes/rows, including rejected inserts, without changing transaction ownership. */
function pool_attach_query(string $sql): array
{
    $link=$GLOBALS['connection'];
    if ($link instanceof mysqli) {
        $result=mysqli_query($link,$sql);
        db_modify_query_trail($link, $sql, [], __FILE__, $result !== false,
            $result === false ? mysqli_error($link) : '');
        if ($result === false) { throw new RuntimeException('Pool identity write failed'); }
        return $result instanceof mysqli_result ? mysqli_fetch_all($result,MYSQLI_ASSOC) : [];
    }
    if (!pg_send_query($link,$sql)) {
        db_modify_query_trail($link, $sql, [], __FILE__, false, pg_last_error($link));
        throw new RuntimeException('Pool identity write failed');
    }
    $result=pg_get_result($link);
    while (pg_get_result($link) !== false) { }
    $ok = $result !== false && in_array(pg_result_status($result), [PGSQL_COMMAND_OK,PGSQL_TUPLES_OK], true);
    db_modify_query_trail($link, $sql, [], __FILE__, $ok,
        $ok ? '' : ($result ? pg_result_error($result) : pg_last_error($link)));
    if (!$result || !in_array(pg_result_status($result),[PGSQL_COMMAND_OK,PGSQL_TUPLES_OK],true)) {
        throw new RuntimeException('Pool identity write failed');
    }
    return pg_num_fields($result) > 0 ? pg_fetch_all($result) : [];

}

/** @return list<int> Every sibling on the legacy voucher; the target is filtered when inserting references. */
function pool_attach_siblings(int $lineId): array
{
    $rows=pool_attach_query("SELECT s.id FROM kassekladde k JOIN kassekladde s ON s.kladde_id=k.kladde_id AND s.bilag=k.bilag WHERE k.id=$lineId AND k.bilag<>0 ORDER BY s.id");
    return array_map(static fn(array $row): int => (int)$row['id'],$rows);
}

/**
 * Write the legacy target/sibling rows. A null identity preserves the web's insert-then-link order.
 * @param array{filepath:string} $target
 * @param list<int> $siblingIds
 * @return list<int> Newly inserted document ids.
 */
function pool_attach_document_rows(array $target, string $fileName, int $lineId, array $siblingIds,
    int $globalId, int $userId, ?int $poolFileId, string $source='kassekladde'): array
{
    $store=new MobileSchema($GLOBALS['connection']);
    $file=$store->literal(basename($fileName));
    $folder=$store->literal($target['filepath']);
    $sourceSql=$store->literal($source);
    $timestamp=time();
    $columns='global_id,filename,filepath,source,source_id,timestamp,user_id';
    $identity=$poolFileId === null ? '' : ','.(int)$poolFileId;
    if ($poolFileId !== null) { $columns.=',pool_file_id'; }
    $ids=[];
    $targets=array_merge([$lineId],array_values(array_diff(array_unique(array_map('intval',$siblingIds)),[$lineId])));
    foreach ($targets as $index=>$id) {
        if ($id <= 0 && $index > 0) { continue; }
        $sql="INSERT INTO documents($columns) SELECT $globalId,$file,$folder,$sourceSql,$id,$timestamp,$userId$identity";
        if ($index > 0) {
            $sql.=" WHERE NOT EXISTS (SELECT 1 FROM documents WHERE source=$sourceSql AND source_id=$id AND filename=$file)";
        }
        if ($GLOBALS['connection'] instanceof mysqli) {
            pool_attach_query($sql);
            if (mysqli_affected_rows($GLOBALS['connection']) > 0) { $ids[]=(int)mysqli_insert_id($GLOBALS['connection']); }
        } else {
            foreach (pool_attach_query($sql.' RETURNING id') as $row) { $ids[]=(int)$row['id']; }
        }
    }
    return $ids;
}

/** @return bool Atomic rename without overwriting an existing file. */
function pool_attach_move(string $from, string $to): bool
{
    clearstatcache(true);
    if (!is_file($from) || is_link($from) || is_link($to)) { return false; }
    if ($from === $to) { return true; }
    return !file_exists($to) && rename($from,$to);
}

/**
 * Validate even a future target against the tenant root and each existing ancestor.
 * @return string Relative storage path; no directories or files are created.
 */
function pool_attach_relative(string $db, string $path): string
{
    if (!preg_match('/\A[A-Za-z0-9_-]+\z/',$db)) { throw new InvalidArgumentException('Invalid tenant'); }
    clearstatcache(true);
    if (!str_starts_with($path,'/')) {
        $parent=realpath(dirname($path));
        if ($parent === false || is_link($path)) { throw new RuntimeException('Invalid attachment path'); }
        $path=$parent.'/'.basename($path);
    }
    $root=dirname(poolPuljePath($db));
    $real=realpath($root);
    if ($real === false || is_link($root) || !str_starts_with($path,$root.'/')) {
        throw new RuntimeException('Attachment outside tenant store');
    }
    $relative=substr($path,strlen($root)+1);
    $current=$root;
    foreach (explode('/',$relative) as $part) {
        if ($part === '' || $part === '.' || $part === '..' || str_contains($part,'\\') || str_contains($part,"\0")) {
            throw new RuntimeException('Invalid attachment path');
        }
        $current.='/'.$part;
        if (is_link($current) || (file_exists($current) && !str_starts_with(realpath($current),$real.'/'))) {
            throw new RuntimeException('Attachment outside tenant store');
        }
    }
    return $relative;
}
