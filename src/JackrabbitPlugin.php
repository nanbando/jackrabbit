<?php

namespace Nanbando\Plugin\Jackrabbit;

use Jackalope\RepositoryFactoryJackrabbit;
use League\Flysystem\Filesystem;
use Nanbando\Core\Database\Database;
use Nanbando\Core\Database\ReadonlyDatabase;
use Nanbando\Core\Plugin\PluginInterface;
use Nanbando\Core\Temporary\TemporaryFileManager;
use PHPCR\ImportUUIDBehaviorInterface;
use PHPCR\SessionInterface;
use PHPCR\SimpleCredentials;
use PHPCR\Util\PathHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JackrabbitPlugin implements PluginInterface
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var TemporaryFileManager
     */
    private $temporaryFileManager;

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output, TemporaryFileManager $temporaryFileManager)
    {
        $this->output = $output;
        $this->temporaryFileManager = $temporaryFileManager;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptionsResolver(OptionsResolver $optionsResolver)
    {
        $optionsResolver
            ->setRequired(
                [
                    'jackrabbit_uri',
                    'workspace',
                    'path',
                    'user',
                    'password',
                ]
            )
            ->setDefaults(
                [
                    'workspace' => 'default',
                    'user' => 'admin',
                    'password' => 'admin'
                ]
            );
    }

    /**
     * {@inheritdoc}
     */
    public function backup(Filesystem $source, Filesystem $destination, Database $database, array $parameter)
    {
        $this->output->writeln('  * <comment>export "' . $parameter['path'] . '" to "export.xml"</comment>');

        $tempfile = $this->temporaryFileManager->getFilename('jackrabbit');
        $stream = fopen($tempfile, 'w+');
        $this->export($this->getSession($parameter), $parameter['path'], $stream);
        fclose($stream);

        $destination->writeStream('export.xml', fopen($tempfile, 'r'));
    }

    /**
     * {@inheritdoc}
     */
    public function restore(
        Filesystem $source,
        Filesystem $destination,
        ReadonlyDatabase $database,
        array $parameter
    ) {
        $this->output->writeln('  * <comment>import "export.xml" to "' . $parameter['path'] . '"</comment>');

        $tempfile = $this->temporaryFileManager->getFilename('jackrabbit');
        $handle = fopen($tempfile, 'w+');
        file_put_contents($tempfile, $source->read('export.xml'));
        fclose($handle);

        $this->import($this->getSession($parameter), $parameter['path'], $tempfile);
    }

    /**
     * Exports workspace.
     *
     * @param SessionInterface $session
     * @param string $path
     * @param resource $stream
     */
    private function export(SessionInterface $session, $path, $stream)
    {
        $session->exportSystemView(
            $path,
            $stream,
            true,
            false
        );
    }

    /**
     * Imports workspace.
     *
     * @param SessionInterface $session
     * @param string $path
     * @param string $fileName
     */
    private function import(SessionInterface $session, $path, $fileName)
    {
        if ($session->nodeExists($path)) {
            $session->getNode($path)->remove();
            $session->save();
        }

        $session->importXML(
            PathHelper::getParentPath($path),
            $fileName,
            ImportUUIDBehaviorInterface::IMPORT_UUID_COLLISION_THROW
        );

        $session->save();
    }

    /**
     * Creates session.
     *
     * @param array $parameter
     *
     * @return SessionInterface
     */
    private function getSession($parameter)
    {
        $factory = new RepositoryFactoryJackrabbit();
        $repository = $factory->getRepository(array('jackalope.jackrabbit_uri' => $parameter['jackrabbit_uri']));
        $credentials = new SimpleCredentials($parameter['user'], $parameter['password']);

        return $repository->login($credentials, $parameter['workspace']);
    }
}
