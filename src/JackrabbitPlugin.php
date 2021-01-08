<?php

namespace Nanbando\Plugin\Jackrabbit;

use Jackalope\RepositoryFactoryJackrabbit;
use League\Flysystem\Filesystem;
use Nanbando\Core\Database\Database;
use Nanbando\Core\Database\ReadonlyDatabase;
use Nanbando\Core\Plugin\PluginInterface;
use Neutron\TemporaryFilesystem\TemporaryFilesystemInterface;
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
     * @var TemporaryFilesystemInterface
     */
    private $temporaryFileSystem;

    /**
     * @param OutputInterface $output
     * @param TemporaryFilesystemInterface $temporaryFileSystem
     */
    public function __construct(OutputInterface $output, TemporaryFilesystemInterface $temporaryFileSystem)
    {
        $this->output = $output;
        $this->temporaryFileSystem = $temporaryFileSystem;
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

        $tempfile = $this->temporaryFileSystem->createTemporaryFile('jackrabbit');
        $stream = fopen($tempfile, 'w+');
        $this->export($this->getSession($parameter), $parameter['path'], $stream);

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

        $tempfile = $this->temporaryFileSystem->createTemporaryFile('jackrabbit');
        $handle = fopen($tempfile, 'w+');
        file_put_contents($tempfile, $source->read('export.xml'));

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
        $memoryStream = \fopen('php://memory', 'w+');
        $session->exportSystemView(
            $path,
            $memoryStream,
            true,
            false
        );

        \rewind($memoryStream);
        $content = \stream_get_contents($memoryStream);

        $document = new \DOMDocument();
        $document->loadXML($content);
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('sv', 'http://www.jcp.org/jcr/sv/1.0');

        foreach ($xpath->query('//sv:property[@sv:name="sulu:versions" or @sv:name="jcr:versionHistory" or @sv:name="jcr:baseVersion" or @sv:name="jcr:predecessors" or @sv:name="jcr:isCheckedOut"]') as $element) {
            $element->parentNode->removeChild($element);
        }

        \fwrite($stream, $document->saveXML());
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
