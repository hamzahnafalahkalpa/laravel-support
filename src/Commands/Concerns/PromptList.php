<?php

namespace Hanafalah\LaravelSupport\Commands\Concerns;

trait PromptList
{
    protected function askMetaCodename()
    {
        $this->info('Collecting meta codename...');
        return \explode(',', $this->ask('Set development codename[s] here !'));
    }

    protected function askUpdateType($options = [])
    {
        do {
            $update_type = $this->choice('Apakah anda ingin naik versi/buat patch baru/tambah developer ?', $options);
            $valid_update_type = !isset($update_type) || !in_array(\strtoupper($update_type), ['UPGRADE', 'PATCH', 'ADD_META_CODENAME']);
        } while ($valid_update_type);
        $this->info('Choosed: ' . $update_type);
        return $update_type;
    }

    protected function askUpgrade($module_list)
    {
        return $this->choice('Pilih Modul yang akan di upgrade/patch ?', $module_list);
    }

    protected function askNewPackage()
    {
        return $this->choice('Ingin buat package baru atau update existing ?', ['new', 'update']);
    }

    protected function askName()
    {
        $package_name = $this->ask('Isi nama package ?');
        $package_name = \to_studly($package_name);
        $this->info('Used Package Name: ' . $package_name);
        return $package_name;
    }
}
