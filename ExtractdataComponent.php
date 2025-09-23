<?php

namespace Apps\Fintech\Components\Etf\Tools\Extractdata;

use Apps\Fintech\Packages\Etf\Tools\Extractdata\EtfToolsExtractdata;
use System\Base\BaseComponent;

class ExtractdataComponent extends BaseComponent
{
    protected $etfToolsExtractDataPackage;

    public function initialize()
    {
        $this->etfToolsExtractDataPackage = $this->usePackage(EtfToolsExtractdata::class);

        $this->setModuleSettings(true);

        $this->setModuleSettingsData([
                'apis' => $this->etfToolsExtractDataPackage->getAvailableApis(true, false),
                'apiClients' => $this->etfToolsExtractDataPackage->getAvailableApis(false, false)
            ]
        );
    }

    /**
     * @acl(name=view)
     */
    public function viewAction()
    {
        $this->view->apis = $this->etfToolsExtractDataPackage->getAvailableApis(false, false);
    }

    public function processAction()
    {
        $this->requestIsPost();

        if ($this->basepackages->progress->checkProgressFile('etfextractdata')) {
            $this->basepackages->progress->deleteProgressFile();
        }

        $this->registerProgressMethods();

        try {
            if ($this->postData()['schemes'] == 'false' &&
                $this->postData()['downloadnav'] == 'false' &&
                $this->postData()['recalculate_portfolios'] == 'false'
            ) {
                $this->addResponse('Nothing selected!', 1);

                return;
            }

            if ($this->postData()['schemes'] == 'true') {
                $this->etfToolsExtractDataPackage->processEtfSchemesData($this->postData());

                if ($this->config->databasetype !== 'db' &&
                    $this->postData()['downloadnav'] != 'true'
                ) {
                    $this->etfToolsExtractDataPackage->reIndexEtfSchemesData();
                }
            }

            if ($this->postData()['downloadnav'] == 'true') {
                $this->etfToolsExtractDataPackage->downloadEtfNavsData();
                $this->etfToolsExtractDataPackage->extractEtfNavsData();
                $this->etfToolsExtractDataPackage->processEtfNavsData($this->postData());
                if ($this->config->databasetype !== 'db' &&
                    $this->postData()['schemes'] != 'true'
                ) {
                    $this->etfToolsExtractDataPackage->reIndexEtfSchemesData();
                }
            }

            if ($this->postData()['recalculate_portfolios'] == 'true') {
                $this->etfToolsExtractDataPackage->recalculatePortfolios();
            }

            $this->addResponse(
                $this->etfToolsExtractDataPackage->packagesData->responseMessage,
                $this->etfToolsExtractDataPackage->packagesData->responseCode,
                $this->etfToolsExtractDataPackage->packagesData->responseData ?? [],
            );
        } catch (\throwable $e) {
            trace([$e]);
            $this->basepackages->progress->preCheckComplete(false);

            $this->basepackages->progress->resetProgress();

            $this->addResponse($e->getMessage(), 1);
        }
    }

    protected function registerProgressMethods()
    {
        $methods = [];

        if ($this->postData()['schemes'] == 'true') {
            $methods = array_merge($methods,
                [
                    [
                        'method'    => 'processEtfSchemesData',
                        'text'      => 'Process Extracted ETF Schemes Data...',
                        'steps'     => true
                    ]
                ]
            );

            if ($this->config->databasetype !== 'db' &&
                $this->postData()['downloadnav'] != 'true'
            ) {
                $methods = array_merge($methods,
                    [
                        [
                            'method'    => 'reIndexEtfSchemesData',
                            'text'      => 'Re-indexing ETF Schemes Data...',
                        ]
                    ]
                );
            }
        }

        if ($this->postData()['downloadnav'] == 'true') {
            $methods = array_merge($methods,
                [
                    [
                        'method'    => 'downloadEtfNavsData',
                        'text'      => 'Download ETF Nav Data...',
                        'remoteWeb' => true
                    ],
                    [
                        'method'    => 'extractEtfNavsData',
                        'text'      => 'Extracting & Indexing ETF Nav Data...',
                        'steps'     => true
                    ],
                    [
                        'method'    => 'processEtfNavsData',
                        'text'      => 'Process Extracted ETF Nav Data...',
                        'steps'     => true
                    ]
                ]
            );

            if ($this->config->databasetype !== 'db' &&
                $this->postData()['schemes'] != 'true'
            ) {
                $methods = array_merge($methods,
                    [
                        [
                            'method'    => 'reIndexEtfSchemesData',
                            'text'      => 'Re-indexing ETF Nav Data...',
                        ]
                    ]
                );
            }
        }

        if ($this->postData()['recalculate_portfolios'] == 'true') {
            $methods = array_merge($methods,
                [
                    [
                        'method'    => 'recalculatePortfolios',
                        'text'      => 'Recalculating Portfolios...',
                        'steps'     => true
                    ]
                ]
            );
        }

        $progress = $this->basepackages->progress->init(null, 'etfextractdata');
        $progress->registerMethods($methods, true);
        $progress->preCheckComplete();

        return true;
    }

    public function getAllNavDataAction()
    {
        $this->requestIsPost();

        if (isset($data['force']) && $data['force'] == 'true') {
            $this->etfToolsExtractDataPackage->processEtfSchemesData($this->postData());
        }

        $this->etfToolsExtractDataPackage->processEtfNavsData($this->postData());

        $this->addResponse(
            $this->etfToolsExtractDataPackage->packagesData->responseMessage,
            $this->etfToolsExtractDataPackage->packagesData->responseCode,
            $this->etfToolsExtractDataPackage->packagesData->responseData ?? []
        );
    }

    public function syncAction()
    {
        $this->requestIsPost();

        $this->etfToolsExtractDataPackage->sync($this->postData());

        $this->addResponse(
            $this->etfToolsExtractDataPackage->packagesData->responseMessage,
            $this->etfToolsExtractDataPackage->packagesData->responseCode
        );
    }
}