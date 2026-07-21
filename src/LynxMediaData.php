<?php namespace ProcessWire;

/** Profile-scoped media upload handling. */
trait LynxMediaData {

    protected function uploadAvatar($profileId) {
        if(empty($_FILES['avatar_file']['name'])) return null;
        $dir = $this->avatarPath();
        if(!is_dir($dir)) wireMkdir($dir, true);

        $u = new WireUpload('avatar_file');
        $this->wire($u);
        $u->setMaxFiles(1);
        $u->setOverwrite(true);
        $u->setDestinationPath($dir);
        $u->setValidExtensions(array('jpg', 'jpeg', 'png', 'gif', 'webp'));
        $u->setMaxFileSize(3 * 1024 * 1024);
        $files = $u->execute();
        if(empty($files)) return null;

        $orig = $dir . $files[0];
        if(@getimagesize($orig) === false) {
            @unlink($orig);
            return null;
        }
        $name = $profileId . '-' . $this->wire('sanitizer')->filename($files[0]);
        $dest = $dir . $name;

        // remove the profile's previous uploaded avatar (only files we manage)
        $prev = $this->getProfile($profileId);
        if($prev && !empty($prev['avatar']) && strpos($prev['avatar'], $this->avatarUrl()) === 0) {
            $prevFile = $dir . basename($prev['avatar']);
            if($prevFile !== $dest && is_file($prevFile)) @unlink($prevFile);
        }

        @rename($orig, $dest);
        return $this->avatarUrl() . $name;
    }
}
