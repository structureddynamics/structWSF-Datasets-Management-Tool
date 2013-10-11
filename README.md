OSF-Datasets-Management-Tool
===========================================

The Datasets Management Tool (DMT) is a command line tool used to manage datasets with a OSF Web Services network instance. All the datasets are configured in the `dmt.ini` file. Different operations can be performed related to datasets management.

The Datasets Management Tool can handle any size of dataset. If the dataset file is too big, the framework will slice it in multiple slices and will send each slice to the OSF Web Services instance.

Installing & Configuring the Datasets Management Tool
-----------------------------------------------------

The Datasets Management Tool can easily be installed on your server using the [OSF-Installer](https://github.com/structureddynamics/Open-Semantic-Framework-Installer):

```bash

  ./osf-installer --install-osf-datasets-management-tool -v
  
```

The DMT is using the [OSF-WS-PHP-API](https://github.com/structureddynamics/OSF-WS-PHP-API) library to communicate with any OSF Web Services network instance. If the OSF-WS-PHP-API is not currently installed on your server, then follow these steps to download and install it on your server instance:

```bash

  ./osf-installer --install-osf-ws-php-api -v 

```

Once the DMT and the OSF-WS-PHP-API are downloaded and properly installed on your server, you then have to configure some key DMT settings. The global DMT configuration options are defined at the top of the `dmt.ini` file, under the `[config]` section. Here is the list of options you can configure:

*   `osfWebServicesFolder`

    > Folder where the OSF-WS-PHP-API is located. This has to be the folder where the 
    > the top "StructuredDynamics" folder appears.
    
*   `indexesFolder`

    > Folder where the checksum of the dataset files are saved
    > This folder is used internally in the DSF. However, if the files got deleted,
    > the all the datasets will be re-indexed

*   `ontologiesStructureFiles`

    > Folder where the propertyHierarchySerialized.srz and the classHierarchySerialized.srz
    > files are located on your server. These files are generated by the Ontology: Read web
    > server endpoint.

*   `missingVocabulary`

    > Folder where the missing vocabulary properties and classes are logged.
    > When you index new datasets into a OSF Web Services instance, it doesn't mean
    > that all the properties and classes that you are using in that dataset
    > are currently defined in the OSF Web Services instance where you are indexed
    > them. Even if they are not defined, they will get indexed. However
    > what is put into this folder are files where you will be able to see
    > which property, or which class, that needs to be added to the ontologies.

*   `memory`

    > Memory available by the script to run. The number is in megabytes.

Configure the Datasets
----------------------

All the datasets that have to be synchronized with a OSF Web Services network instance needs to be defined in the `dmt.ini` file. A series of required, and optional, configuration options can be defined for each dataset to be imported.

What the DMT does is to read one, or multiple RDF files serialized in XML or in N3, that composes the dataset to index. Each of the dataset file(s) can be in the same folder, or in any other folder configuration. The only thing that needs to be done is to properly configure the `datasetLocalPath` configuration option for each dataset.

Here is an example of such a dataset configuration:

```bash
  [Foo-Dataset]
  datasetURI = "http://foobar.com/datasets/documents/"
  baseURI = "http://foobar.com/datasets/documents/"
  datasetLocalPath = "/data/sync/data/"
  converterPath = "/data/sync/converters/default/"
  converterScript = "defaultConverter.php"
  converterFunctionName = "defaultConverter"
  baseOntologyURI = "http://purl.org/ontology/foo#"
  sliceSize = "50"
  targetOSFWebServices = "https://foobar.com/ws/"
  filteredFiles = "my-serialized-dataset-records.n3"
  forceReload = "true"
  title = "This is the title of my new dataset"
  description = "This is a description of my new dataset"
  creator = "http://foobar.com/user/1"
```

The name of the dataset, within the DMT, is `Foo-Dataset`. Each of these names have to be unique within the dmt.ini file. What we configure here is information about the dataset, how it should be created and where.

Let's take a look at each configuration option that are current available:




### Network Configuration Options


*   `targetOSFWebServices` - *required*

    > This parameter is the URL of the OSF Web Services instance where the records have to be created. 
    > Note that the dataset has to be existing on that OSF Web Services instance before running 
    > the syncing script. Also note that the server that perform the sync has to have the 
    > proper rights to write information into that dataset on that OSF Web Services instance.

*   `targetOSFWebServicesQueryExtension` - *optional*

    > This parameter is used to specify a possible QuerierExtension
    > if it is required by the OSF Web Services instance to query. You have to specify the full QuerierExtension
    > which includes the possible namespace in it, like: 
    >   `StructuredDynamics\osf\framework\FooQuerierExtension`

### Dataset Configuration Options

*   `datasetURI` - *required*

    > This parameter is the URI of the dataset to update in the OSF Web Services instance

*   `baseURI` - *required*

    > This parameter is the base URI of the records that get converted. If the base URI
    > is not defined within the rdf serialized files, this URI is being used to create
    > the complete URI for these records. 

*   `datasetLocalPath` - *required*

    > This parameter is the local path folder where the file(s) of this dataset are available

### Converter Configuration Options    
    
*   `converterScript` - *required*

    > This parameter is the name of the converter PHP script to run to convert and import into the dataset    

*   `converterFunctionName` - *required*

    > This is the name of the function to call that will convert a list of fils into RDF. 
    > It takes two parameters, the first one is the path of a file to conver and the second 
    > parameter is the parsed INI processing section of this file for this dataset.

*   `converterPath` - *required*

    > This parameter is the path where all files of the converter are located

*   `baseOntologyURI` - *optional*

    > This parameter is used by the converter of the dataset to properly create the new properties 
    > and classes while converting the dataset. This parameter is optional to some converter

### Dataset Importation Options    
    
*   `sliceSize` - *optional*

    > This defines the number of record to send to the CRUD: Create OSF Web Services endpoint at 
      each time. Tweaking this parameter have an impact on the performences for the syncing process
      along with the required memory to run DMT. Also, if the network to get to the OSF Web Services instance
      is defined with short timeouts for the connections, then smaller size slices may enable
      the DMT not to get timeouted.

*   `filteredFiles` - *optional*

    > This parameter is used to filter down to a file, or a set of files for that dataset. 
      Each file name are seperated by a semi-colon ";".

*   `filteredFilesRegex` - *optional*

    > This has the same behavior as the "filteredFiles" parameter but it does match files to 
      include into the dataset based on a regex patter. This parameter has priority on `filteredFiles`.

*   `forceReload` - *optional*

    > This parameter is used to specify that each time sync.php is run
    > that we want to reload the dataset. Reloading the dataset means that the
    > dataset get deleted, recreated and re-imported into the OSF Web Services instance.
    > This parameter will be considered when: forceReload = "true"
    > IMPORTANT NOTE: this means that all the modifications that haven't been
    >                 saved in the serialized file used by the DMT will be lost!!

*   `forceReloadSolrIndex` - *optional*

    > This parameter is used to specify that each time sync.php is run
    > that we want to reload the content of the dataset in Solr. This means that the data
    > in the triple store is unchanged, but that the dataset in Solr is delete and
    > re-created from what is indexed in the triple store. This means that the data
    > doesn't change. This should be used for re-indexing data into Solr. This normally
    > happen each time we change the Solr index, or each time we modify the way
    > CRUD: Create or CRUD: Update index content in Solr.
    
### Dataset Creation Configurations

*   `title` - *required*

    > This parameter is used to specify the title to use if the dataset needs to be
    > created by the Dataset Synchronization Framework.

*   `description` - *required*

    > parameter is used to specify the description to use if the dataset needs to be
    > created by the Dataset Synchronization Framework.

*   `creator` - *required*

    > This parameter is used to specify the creator's URI to use if the dataset needs to be
    > created by the Dataset Synchronization Framework.

Converters
----------

Here is the list of all the data convertion scripts that are currently available in the DMT. All these scripts does use a certain format as input and convert it into RDF+XML or RDF+N3 and index the converted RDF data into the OSF Web Services network instance.

#### defaultConverter

This converter does index RDF+N3 or RDF+XML data directly into the OSF Web Services network instance.

Usage Documentation
-------------------
```
Usage: dmt [OPTIONS]


Usage examples:
    Synchronizing all datasets: dmt -s
    Synchronizing a single dataset: dmt -s --config-id="My-Dataset-Id-From-Sync.ini"
    Deleting a dataset: dmt -d --uri="http://.../uri/of/record/to/delete/" --osf-web-services="http://osf-web-services-instance.com/ws/"
    Creating an empty dataset: dmt --title="Foo" -n --uri="http://.../uri/of/record/to/delete/" --osf-web-services="http://osf-web-services-instance.com/ws/"
    List existing datasets: dmt -l --osf-web-services="http://osf-web-services-instance.com/ws/"
Options:
-s                                    Synchronize all the datasets listed in dmt.ini
-n                                    Create a new empty dataset
-d                                    Delete an existing dataset
-l                                    List all the accessible datasets
-e                                    Export a dataset
-h, --help                            Show this help section

Dataset Creation Options:
--osf-web-services="[URL]"                   (required) Target OSF Web Services network endpoints URL.
                                                 Example: 'http://localhost/ws/'
--uri="[URI]"                         (required) URI of the dataset to create
--title="[TITLE]"                     (required) Title of the new dataset
--creator="[URI]"                     (optional) URI of the creator of this dataset
--description="[DESCRIPTION]"         (optional) Description of the new dataset
--queryextension="[CLASS]"            (optional) Class of the QueryExtension to use for creating this new dataset.
                                                 The class should include the full namespace.'
                                                 Example: 'StructuredDynamics\osf\framework\MyQuerierExtension'
Dataset Deletation Options:
--osf-web-services="[URL]"                   (required) Target OSF Web Services network endpoints URL.
                                                 Example: 'http://localhost/ws/'
Dataset Export Options:
--osf-web-services="[URL]"                   (required) Target OSF Web Services network endpoints URL.
                                                 Example: 'http://localhost/ws/'
--file="[FILE]"                       (required) File where to export the dataset.
                                                 Example: '/tmp/my_exported_dataset.rdf'
--mime="[URL]"                        (optional) Specify the export format; One of:
                                                 (a) application/rdf+xml (default)'
                                                 (b) application/rdf+n3'
--uri="[URI]"                         (optional) URI of the dataset to create

Dataset Listing Options:
--osf-web-services="[URL]"                   (required) Target OSF Web Services network endpoints URL.
                                                 Example: 'http://localhost/ws/'
Configuration File Options:
-c [FILE]                             Specifies the configuration file to use. Can include the
                                      full path. If the full path is not specified, the DSF
                                      will try to find it from the current folder.
--config-id="[ID]"                    (optional) Dataset ID, within the target configuration file,
                                                 to run for this query
```

Create, Update and Delete records within datasets
-------------------------------------------------

Sometimes we have to only update the delta(s) between two version of the same data source. This means that some new records may need to be created, a few others may have to be updated and a few more to be deleted from an existing dataset. These operations can easily be done without reloading the entire dataset into OSF Web Services.

When the DMT synchronize a dataset, it does analyze the content that is being indexed into OSF Web Services, one of the analyze step that is performed by the framework is to check if the records that are being synchronized are described using the `http://purl.org/ontology/wsf#crudAction` property with one of the following value:

* create
* update
* delete

The `wsf:crudAction` property is used to instruct the DMT to perform different actions depending on the value of the property. If the value is `create`, then this means that the DMT has to create that record in the dataset. If the value is `update`, then this means that the record has to be updated in the dataset. Finally, if the value is `delete`, then this means that the dataset has to be deleted from the dataset.

If the `wsf:Action` property is not used to define a record, then `create` is assumed by the DMT.

This is the machanism that is used to synchronize datasets to OSF Web Services using the DMT. If nothing is specified, then records are simply created.


Missing Properties and Classes
------------------------------

Folder where the missing vocabulary properties and classes are logged. When you index new datasets into a OSF Web Services instance, it doesn't mean that all the properties and classes that you are using in that dataset are currently defined in the OSF Web Services instance where you are indexed them. Even if they are not defined, they will get indexed. However what is put into this folder are files where you will be able to see which property, or which class, that needs to be added to the ontologies.
