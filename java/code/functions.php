<?php

/**
 * 下载代码
 * @param string $codePath
 * @param string $prName
 * @param string $codeUrl
 * @param string $authInfo
 * @return void
 */
function downCode(string $codePath, string $prName, string $codeUrl, array $authInfo)
{
    $is_private = $authInfo['is_private'] ?? 0;
    $username = $authInfo['username'] ?? '';
    $password = $authInfo['password'] ?? '';
    $key = $authInfo['key'] ?? '';


    if ($is_private) {  // 私有仓库
        preg_match('/^(http|https):\/\//', $codeUrl, $agreement);
        if (!$agreement) {   // ssh拉取
            $filename = "{$codePath}/id_rsa/";
            if (!file_exists($filename)) {
                mkdir($filename, 0777);
            }
            $filename .= uniqid() . '_id_rsa';
            file_put_contents($filename, $key);
            systemLog("chmod 600 {$filename}");
            systemLog('git config --global core.sshCommand "ssh -i ' . $filename . '"');
        } else {
            $codeUrl = "{$agreement[0]}{$username}:{$password}@" . substr($codeUrl, 8, strlen($codeUrl));
        }
    }
    if (!file_exists("{$codePath}/{$prName}")) {
        $cmd = "cd {$codePath}/ && git clone --depth=1 {$codeUrl}  $prName";
        systemLog($cmd);
    } else {
        $cmd = "cd {$codePath}/{$prName} && git pull ";
        systemLog($cmd);
    }

    return true;
}