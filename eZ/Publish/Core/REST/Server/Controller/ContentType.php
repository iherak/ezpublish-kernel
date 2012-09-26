<?php
/**
 * File containing the ContentType controller class
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\REST\Server\Controller;
use eZ\Publish\Core\REST\Common\UrlHandler;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\Core\REST\Common\Message;
use eZ\Publish\Core\REST\Common\Input;
use eZ\Publish\Core\REST\Common\Exceptions;
use eZ\Publish\Core\REST\Server\Exceptions\ForbiddenException;
use eZ\Publish\API\Repository\Exceptions\InvalidArgumentException;

use eZ\Publish\Core\REST\Server\Values;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroupCreateStruct;
use eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroupUpdateStruct;

use Qafoo\RMF;

/**
 * ContentType controller
 */
class ContentType
{
    /**
     * Input dispatcher
     *
     * @var \eZ\Publish\Core\REST\Common\Input\Dispatcher
     */
    protected $inputDispatcher;

    /**
     * URL handler
     *
     * @var \eZ\Publish\Core\REST\Common\UrlHandler
     */
    protected $urlHandler;

    /**
     * Content type service
     *
     * @var \eZ\Publish\API\Repository\ContentTypeService
     */
    protected $contentTypeService;

    /**
     * Construct controller
     *
     * @param \eZ\Publish\Core\REST\Common\Input\Dispatcher $inputDispatcher
     * @param \eZ\Publish\Core\REST\Common\UrlHandler $urlHandler
     * @param \eZ\Publish\API\Repository\ContentTypeService $contentTypeService
     */
    public function __construct( Input\Dispatcher $inputDispatcher, UrlHandler $urlHandler, ContentTypeService $contentTypeService )
    {
        $this->inputDispatcher = $inputDispatcher;
        $this->urlHandler = $urlHandler;
        $this->contentTypeService = $contentTypeService;
    }

    /**
     * Creates a new content type group
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\CreatedContentTypeGroup
     */
    public function createContentTypeGroup( RMF\Request $request )
    {
        $createStruct = $this->inputDispatcher->parse(
            new Message(
                array( 'Content-Type' => $request->contentType ),
                $request->body
            )
        );

        try
        {
            return new Values\CreatedContentTypeGroup(
                array(
                    'contentTypeGroup' => $this->contentTypeService->createContentTypeGroup( $createStruct )
                )
            );
        }
        catch ( InvalidArgumentException $e )
        {
            throw new ForbiddenException( $e->getMessage() );
        }
    }

    /**
     * Updates a content type group
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup
     */
    public function updateContentTypeGroup( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'typegroup', $request->path );

        $createStruct = $this->inputDispatcher->parse(
            new Message(
                array( 'Content-Type' => $request->contentType ),
                $request->body
            )
        );

        try
        {
            $this->contentTypeService->updateContentTypeGroup(
                $this->contentTypeService->loadContentTypeGroup( $urlValues['typegroup'] ),
                $this->mapToGroupUpdateStruct( $createStruct )
            );

            return $this->contentTypeService->loadContentTypeGroup( $urlValues['typegroup'] );
        }
        catch ( InvalidArgumentException $e )
        {
            throw new ForbiddenException( $e->getMessage() );
        }
    }

    /**
     * The given content type group is deleted
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\ResourceDeleted
     */
    public function deleteContentTypeGroup( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'typegroup', $request->path );

        $contentTypeGroup = $this->contentTypeService->loadContentTypeGroup( $urlValues['typegroup'] );

        $contentTypes = $this->contentTypeService->loadContentTypes( $contentTypeGroup );
        if ( !empty( $contentTypes ) )
        {
            throw new ForbiddenException( 'Only empty content type groups can be deleted' );
        }

        $this->contentTypeService->deleteContentTypeGroup( $contentTypeGroup );

        return new Values\ResourceDeleted();
    }

    /**
     * Returns a list of content types of the group
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\ContentTypeList|\eZ\Publish\Core\REST\Server\Values\ContentTypeInfoList
     */
    public function listContentTypesForGroup( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'grouptypes', $request->path );

        $contentTypes = $this->contentTypeService->loadContentTypes(
            $this->contentTypeService->loadContentTypeGroup( $urlValues['typegroup'] )
        );

        if ( $this->getMediaType( $request ) == 'application/vnd.ez.api.contenttypelist' )
        {
            return new Values\ContentTypeList( $contentTypes, $request->path );
        }

        return new Values\ContentTypeInfoList( $contentTypes, $request->path );
    }

    /**
     * Returns a list of all content type groups
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\ContentTypeGroupList
     */
    public function loadContentTypeGroupList( RMF\Request $request )
    {
        return new Values\ContentTypeGroupList(
            $this->contentTypeService->loadContentTypeGroups()
        );
    }

    /**
     * Returns the content type group given by id
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroup
     */
    public function loadContentTypeGroup( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'typegroup', $request->path );

        return $this->contentTypeService->loadContentTypeGroup( $urlValues['typegroup'] );
    }

    /**
     * Load a content info by remote ID
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\API\Repository\Values\ContentType\ContentType
     */
    public function loadContentType( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'type', $request->path );

        $contentType = $this->contentTypeService->loadContentType( $urlValues['type'] );

        return new Values\RestContentType(
            $contentType,
            $contentType->getFieldDefinitions()
        );
    }

    /**
     * Creates a new content type draft in the given content type group
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\CreatedContentType
     */
    public function createContentType( RMF\Request $request )
    {
        $questionMarkPosition = strpos( $request->path, '?' );
        $urlValues = $this->urlHandler->parse(
            'grouptypes',
            $questionMarkPosition !== false ? substr( $request->path, 0, $questionMarkPosition ) : $request->path
        );

        //@todo Throw forbidden exception if content type identifier already exists
        //Cannot be caught as PAPI throws same InvalidArgumentException for couple of situations

        $contentTypeDraft = $this->contentTypeService->createContentType(
            $this->inputDispatcher->parse(
                new Message(
                    array(
                        'Content-Type' => $request->contentType,
                    ),
                    $request->body
                )
            ),
            array( $this->contentTypeService->loadContentTypeGroup( $urlValues['typegroup'] ) )
        );

        if ( isset( $request->variables['publish'] ) && $request->variables['publish'] === 'true' )
        {
            $this->contentTypeService->publishContentTypeDraft( $contentTypeDraft, 'bla' );

            $contentType = $this->contentTypeService->loadContentType( $contentTypeDraft->id );
            return new Values\CreatedContentType(
                array(
                    'contentType' => new Values\RestContentType(
                        $contentType,
                        $contentType->getFieldDefinitions()
                    )
                )
            );
        }

        return new Values\CreatedContentType(
            array(
                'contentType' => new Values\RestContentType(
                    $contentTypeDraft,
                    $contentTypeDraft->getFieldDefinitions()
                )
            )
        );
    }

    /**
     * Copies a content type. The identifier of the copy
     * is changed to copy_of_<identifier> and a new remoteId is generated.
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\ResourceCreated
     */
    public function copyContentType( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'type', $request->path );

        $copiedContentType = $this->contentTypeService->copyContentType(
            $this->contentTypeService->loadContentType( $urlValues['type'] )
        );

        return new Values\ResourceCreated(
            $this->urlHandler->generate( 'type', array( 'type' => $copiedContentType->id ) )
        );
    }

    /**
     * Creates a draft and updates it with the given data
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\CreatedContentType
     */
    public function createContentTypeDraft( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'type', $request->path );

        // @TODO Throw ForbiddenException if the content type already has a draft

        $contentTypeDraft = $this->contentTypeService->createContentTypeDraft(
            $this->contentTypeService->loadContentType( $urlValues['type'] )
        );

        $contentTypeUpdateStruct = $this->inputDispatcher->parse(
            new Message(
                array(
                    'Content-Type' => $request->contentType,
                ),
                $request->body
            )
        );

        // @TODO Throw ForbiddenException if the content type with the identifier already exists
        // PAPI throws same exception for various situations

        $this->contentTypeService->updateContentTypeDraft(
            $contentTypeDraft,
            $contentTypeUpdateStruct
        );

        return new Values\CreatedContentType(
            array(
                'contentType' => new Values\RestContentType(
                    // Reload the content type draft to get the updated values
                    $this->contentTypeService->loadContentTypeDraft(
                        $contentTypeDraft->id
                    )
                )
            )
        );
    }

    /**
     * Updates meta data of a draft. This method does not handle field definitions
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\RestContentType
     */
    public function updateContentTypeDraft( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'typeDraft', $request->path );

        // @TODO Throw NotFoundException if the content type does not have a draft

        $contentTypeDraft = $this->contentTypeService->loadContentTypeDraft( $urlValues['type'] );
        $contentTypeUpdateStruct = $this->inputDispatcher->parse(
            new Message(
                array(
                    'Content-Type' => $request->contentType,
                ),
                $request->body
            )
        );

        // @TODO Throw ForbiddenException if the content type with the identifier already exists
        // PAPI throws same exception for various situations

        $this->contentTypeService->updateContentTypeDraft(
            $contentTypeDraft,
            $contentTypeUpdateStruct
        );

        return new Values\RestContentType(
            // Reload the content type draft to get the updated values
            $this->contentTypeService->loadContentTypeDraft(
                $contentTypeDraft->id
            )
        );
    }

    /**
     * Creates a new field definition for the given content type
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\FieldDefinitionList
     */
    public function addFieldDefinition( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'typeFieldDefinitionsDraft', $request->path );

        $contentTypeDraft = $this->contentTypeService->loadContentTypeDraft( $urlValues['type'] );
        $fieldDefinitionCreate = $this->inputDispatcher->parse(
            new Message(
                array(
                    'Content-Type' => $request->contentType,
                ),
                $request->body
            )
        );

        try
        {
            $this->contentTypeService->addFieldDefinition(
                $contentTypeDraft,
                $fieldDefinitionCreate
            );
        }
        catch ( InvalidArgumentException $e )
        {
            throw new ForbiddenException( $e->getMessage() );
        }

        $updatedDraft = $this->contentTypeService->loadContentTypeDraft( $urlValues['type'] );
        foreach ( $updatedDraft->getFieldDefinitions() as $fieldDefinition )
        {
            if ( $fieldDefinition->identifier == $fieldDefinitionCreate->identifier )
            {
                return new Values\CreatedFieldDefinition(
                    array(
                        'fieldDefinition' => new Values\RestFieldDefinition(
                            $updatedDraft, $fieldDefinition
                        )
                    )
                );
            }
        }

        throw new Exceptions\NotFoundException( "Field definition not found: '{$request->path}'." );
    }

    /**
     * Loads field definitions for a given content type
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\FieldDefinitionList
     */
    public function loadFieldDefinitionList( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'typeFieldDefinitions', $request->path );

        $contentType = $this->contentTypeService->loadContentType( $urlValues['type'] );

        return new Values\FieldDefinitionList(
            $contentType,
            $contentType->getFieldDefinitions()
        );
    }

    /**
     * Returns the field definition given by id
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\RestFieldDefinition
     */
    public function loadFieldDefinition( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'typeFieldDefinition', $request->path );

        $contentType = $this->contentTypeService->loadContentType( $urlValues['type'] );

        foreach ( $contentType->getFieldDefinitions() as $fieldDefinition )
        {
            if ( $fieldDefinition->id == $urlValues['fieldDefinition'] )
            {
                return new Values\RestFieldDefinition(
                    $contentType,
                    $fieldDefinition
                );
            }
        }

        throw new Exceptions\NotFoundException( "Field definition not found: '{$request->path}'." );
    }

    /**
     * Loads field definitions for a given content type draft
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\FieldDefinitionList
     */
    public function loadDraftFieldDefinitionList( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'typeFieldDefinitionsDraft', $request->path );

        $contentTypeDraft = $this->contentTypeService->loadContentTypeDraft( $urlValues['type'] );

        return new Values\FieldDefinitionList(
            $contentTypeDraft,
            $contentTypeDraft->getFieldDefinitions()
        );
    }

    /**
     * Returns the draft field definition given by id
     *
     * @param RMF\Request $request
     * @return \eZ\Publish\Core\REST\Server\Values\RestFieldDefinition
     */
    public function loadDraftFieldDefinition( RMF\Request $request )
    {
        $urlValues = $this->urlHandler->parse( 'typeFieldDefinitionDraft', $request->path );

        $contentTypeDraft = $this->contentTypeService->loadContentTypeDraft( $urlValues['type'] );

        foreach ( $contentTypeDraft->getFieldDefinitions() as $fieldDefinition )
        {
            if ( $fieldDefinition->id == $urlValues['fieldDefinition'] )
            {
                return new Values\RestFieldDefinition(
                    $contentTypeDraft,
                    $fieldDefinition
                );
            }
        }

        throw new Exceptions\NotFoundException( "Field definition not found: '{$request->path}'." );
    }

    /**
     * Converts the provided ContentTypeGroupCreateStruct to ContentTypeGroupUpdateStruct
     *
     * @param \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroupCreateStruct $createStruct
     * @return \eZ\Publish\API\Repository\Values\ContentType\ContentTypeGroupUpdateStruct
     */
    private function mapToGroupUpdateStruct( ContentTypeGroupCreateStruct $createStruct )
    {
        return new ContentTypeGroupUpdateStruct(
            array(
                'identifier' => $createStruct->identifier,
                'modifierId' => $createStruct->creatorId,
                'modificationDate' => $createStruct->creationDate,
                'mainLanguageCode' => $createStruct->mainLanguageCode,
                'names' => $createStruct->names,
                'descriptions' => $createStruct->descriptions
            )
        );
    }

    /**
     * Extracts the requested media type from $request
     *
     * @param RMF\Request $request
     * @return string
     */
    private function getMediaType( RMF\Request $request )
    {
        foreach ( $request->mimetype as $mimeType )
        {
            if ( preg_match( '(^([a-z0-9-/.]+)\+.*$)', $mimeType['value'], $matches ) )
            {
                return $matches[1];
            }
        }
        return 'unknown/unknown';
    }
}
