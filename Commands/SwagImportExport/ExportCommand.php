<?php

namespace Shopware\Commands\SwagImportExport;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Shopware\Components\SwagImportExport\Utils\CommandHelper;

class ExportCommand extends ShopwareCommand
{

    protected $profile;
    protected $profileEntity;
    protected $exportVariants;
    protected $limit;
    protected $offset;
    protected $format;
    protected $filePath;
    protected $sessionId;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('sw:importexport:export')
                ->setDescription('Export data to files.')
                ->addArgument('filepath', InputArgument::REQUIRED, 'Path to file to read from.')
                ->addOption('profile', 'p', InputOption::VALUE_REQUIRED, 'Which profile will be used?', null)
                ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'What is the format of the imported file - XML or CSV?', null)
                ->addOption('exportVariants', 'x', InputOption::VALUE_NONE, 'Should the variants be exported?', null)
                ->addOption('offset', 'o', InputOption::VALUE_REQUIRED, 'What is the offset?', null)
                ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'What is the limit?', null)
                ->setHelp("The <info>%command.name%</info> imports data from a file.");
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Validation of user input
        $this->prepareExportInputValidation($input, $output);

        $this->registerErrorHandler($output);
        
        $helper = new CommandHelper(array(
            'profileEntity' => $this->profileEntity,
            'filePath' => $this->filePath,
            'format' => $this->format,
            'exportVariants' => $this->exportVariants,
            'limit' => $this->limit,
            'offset' => $this->offset,
        ));

        $output->writeln('<info>' . sprintf("Using profile: %s.", $this->profile) . '</info>');
        $output->writeln('<info>' . sprintf("Using format: %s.", $this->format) . '</info>');
        $output->writeln('<info>' . sprintf("Using file: %s.", $this->filePath) . '</info>');

        $return = $helper->prepareExport();
        $count = $return['count'];
        $output->writeln('<info>' . sprintf("Total count: %d.", $count) . '</info>');

        $data = $helper->exportAction();
        $position = $data['position'];
        $output->writeln('<info>' . sprintf("Processed: %d.", $position) . '</info>');

        while ($position < $count) {
            $data = $helper->exportAction();
            $position = $data['position'];
            $output->writeln('<info>' . sprintf("Processed: %d.", $position) . '</info>');
        }
    }

    protected function prepareExportInputValidation(InputInterface $input, OutputInterface $output)
    {
        $this->profile = $input->getOption('profile');
        $this->format = $input->getOption('format');
        $this->exportVariants = $input->getOption('exportVariants');
        $this->offset = $input->getOption('offset');
        $this->limit = $input->getOption('limit');
        $this->filePath = $input->getArgument('filepath');

        $parts = explode('.', $this->filePath);
        $count = count($parts);

        // get some service from container (formerly Shopware()->Bootstrap()->getResource())
        $em = $this->container->get('models');

        $profileRepository = $em->getRepository('Shopware\CustomModels\ImportExport\Profile');

        // if no profile is specified try to find it from the filename
        if ($this->profile === NULL) {
            foreach ($parts as $part) {
                $part = strtolower($part);
                $this->profileEntity = $profileRepository->findOneBy(array('name' => $part));
                if ($this->profileEntity !== NULL) {
                    $this->profile = $part;
                    break;
                }
            }
        } else {
            $this->profileEntity = $profileRepository->findOneBy(array('name' => $this->profile));
        }

        // validate profile
        if (!$this->profileEntity) {
            throw new \Exception(sprintf('Invalid profile: \'%s\'!', $this->profile));
        }

        // if no format is specified try to find it from the filename
        if (empty($this->format)) {
            $this->format = pathinfo($this->filePath, PATHINFO_EXTENSION);
        }
        
        // format should be case insensitive
        $this->format = strtolower($this->format);
        
        // validate type
        if (!in_array($this->format, array('csv', 'xml'))) {
            throw new \Exception(sprintf('Invalid format: \'%s\'! Valid formats are: CSV and XML.', $this->format));
        }
    }

    protected function Plugin()
    {
        return Shopware()->Plugins()->Backend()->SwagImportExport();
    }

}
