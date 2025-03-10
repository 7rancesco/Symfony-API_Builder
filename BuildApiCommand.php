<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

#[AsCommand(
    name: 'app:build-api',
    description: 'Add a short description for your command',
)]
class BuildApiCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    private function makeEntity($entity_name)
    {
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        $listExtractors = [$reflectionExtractor];
        $typeExtractors = [$phpDocExtractor, $reflectionExtractor];
        $descriptionExtractors = [$phpDocExtractor];
        $accessExtractors = [$reflectionExtractor];
        $propertyInitializableExtractors = [$reflectionExtractor];

        $propertyInfo = new PropertyInfoExtractor(
            $listExtractors,
            $typeExtractors,
            $descriptionExtractors,
            $accessExtractors,
            $propertyInitializableExtractors
        );

        $entity = 'App\Entity\\' . $entity_name;
        $properties = $propertyInfo->getProperties($entity);

        $reflection = new \ReflectionClass($entity);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        $methods = array_values(array_filter($methods, fn($method) => $method->name !== '__construct'));
        function getType($entity, $propertyInfo, $property, $methods, $index)
        {
            $method= $methods[$index]->name;
            if($index > 0)
            {
                $method= $methods[($index * 2) - 1]->name." ".$methods[$index * 2]->name;
            }
            $types= $propertyInfo->getTypes($entity, $property);
            $type = $types[0]->getBuiltInType();
            if($type == 'object')
            {
                $classType= $types[0]->getClassName();
                if($classType == 'DateTimeInterface')
                {
                    $type= '\DateTime';
                }
                if($classType == 'Doctrine\Common\Collections\Collection')
                {
                    $type= 'Collection';
                }
            }
            $nullable = $types[0]->isNullable();
            $info = array(
                "property" => $property,
                "nullable" => $nullable,
                "type" => $type,
                "getter" => explode(" ", $method)[0],
            );
            if(count(explode(" ", $method)) == 2)
            {
                $info['setter'] = explode(" ", $method)[1];
            }
            return $info;
        }

        $types = array_map(function ($property, $index) use ($entity, $propertyInfo, $methods) {
            return getType($entity, $propertyInfo, $property, $methods, $index);
        }, $properties, array_keys($properties));

        return $types;
    }

    private function makeBaseDTO($entity, $className)
    {
        $DTO_file= "";
        foreach ($entity as $property) {
            $property_name= $property['property'];
            $type= "?".$property['type'];
            $default_value= 'null';
            if(!$property['nullable'])
            {
                if($type == '?string')
                {
                    $default_value= "''";
                }
            }
            if($type == '?object' || $type == '?Collection')
            {
                $type= "mixed";
            }
            $DTO_file.= "     public $type $$property_name = $default_value;\n";
        }
        return $DTO_file;
    }

    private function makeDTO($entity, $className)
    {
        $DTO_file= "";
        foreach ($entity as $property) {
            $property_name= $property['property'];
            $getter= $property['getter'];
            $type= $property['type'];
            $fullGetter= "$$className->$getter()";
            if($type == 'object')
            {
                $dtoName= ucfirst($property_name);
                $fullGetter.= " ? new $dtoName"."DTO($$className->$getter()) : null";
            }
            if($type == 'Collection')
            {
                $fullGetter.= "->map(fn(\$item) => \$item->getId())->toArray()";
            }
            $DTO_file.= "\n             \$this->$property_name = $fullGetter;";
        }
        return $DTO_file;
    }

    private function makeService($entity, $className)
    {
        $DTO_file= "";
        foreach ($entity as $property) {
            $property_name= $property['property'];
            if(array_key_exists('setter', $property)){
                $base_setter= $property['setter'];
                $setter="if(array_key_exists('$property_name', \$k)){ $$className->$base_setter(\$dto->$property_name); }";
                if($property['type'] == 'object')
                {
                    $entName= ucfirst($property_name);
                    $setter="if(array_key_exists('$property_name', \$k)){ $$className->$base_setter(\$this->em->getRepository('App\Entity\\".$entName."')->find(\$dto->$property_name)); }";
                }
                if($property['type'] == 'Collection')
                {
                    $entName= str_replace('add', '', $base_setter);
                    $getAll= $property['getter'];
                    $setter= "
                       if(array_key_exists('$property_name', \$k))
                       {
                           foreach ($$className->$getAll() as \$item) {
                               $$className"."->remove$entName(\$item);
                           }
                           foreach (\$this->em->getRepository('App\Entity\\$entName')->findBy(['id' => \$dto->$property_name]) as \$item) {
                               $$className->$base_setter(\$item);
                           }
                       }                    
                    ";
                }
                $DTO_file.= "   $setter\n";
            }
        }
        return $DTO_file;
    }

    private function getFile($__ClassName, $__Content, $template, $destination)
    {
        $path= __DIR__."/Template/$template.txt";
        $handle = fopen($path, "r");
        $contents = fread($handle, filesize($path));
        fclose($handle);
        $contents= str_replace('__ClassName', $__ClassName, $contents);
        $contents= str_replace('//__Content', $__Content, $contents);
        $filePath = str_replace('Command', '', __DIR__)."$destination";
        if (!is_dir($filePath)) {
            mkdir($filePath, 0777, true);
        }
        $filePath.= "/$__ClassName$template.php";
        file_put_contents($filePath, $contents);
        echo "Created: $destination/$__ClassName$template.php\n";
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('make-ts-api', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');

        if ($arg1) {
            $io->note(sprintf('You passed class: %s', $arg1));
            $entity= $this->makeEntity($arg1);

            $this->getFile(
                $arg1,
                $this->makeDTO(
                    $entity,
                    $arg1
                ),
                "DTO",
                "DTO"
            );

            $this->getFile(
                $arg1,
                $this->makeBaseDTO(
                    $entity,
                    $arg1
                ),
                "BaseDTO",
                "DTO"
            );
            $this->getFile(
                $arg1,
                $this->makeService(
                    $entity,
                    $arg1
                ),
                "Service",
                "Service"
            );
        }

        if ($input->getOption('make-ts-api')) {

            echo "make-ts-api passed";
        }

        $io->success('Done');

        return Command::SUCCESS;
    }
}
