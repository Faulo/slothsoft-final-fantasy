<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:sse="http://schema.slothsoft.net/savegame/editor"
                xmlns="http://schema.slothsoft.net/final-fantasy/i" exclude-result-prefixes="sse">
    <xsl:output method="xml" encoding="UTF-8" indent="yes" />
    <xsl:param name="rom-sha256" />
    <xsl:key name="name" match="sse:group[sse:string[@name='text']]" use="@name" />
    <xsl:variable name="file" select="/sse:savegame/sse:archive/sse:file" />
    <xsl:variable name="prices" select="$file/sse:instruction[@name='prices']/sse:group" />
    <xsl:template match="/">
        <final-fantasy version="1.0" region="US" rom-sha256="{$rom-sha256}">
            <classes>
                <xsl:for-each select="$file/sse:group[@name='names']/sse:group[number(substring-after(@name, 'name-')) &gt;= 240]">
                    <class id="{number(substring-after(@name, 'name-')) - 240}" name="{sse:string/@value}" />
                </xsl:for-each>
            </classes>
            <weapons>
                <xsl:for-each select="$file/sse:instruction[@name='weapons']/sse:group">
                    <xsl:variable name="id" select="position()" />
                    <!-- The original US battle code uses the weapon ID as its critical threshold.
                         Its stored critical, elemental and category fields are not applied. -->
                    <weapon id="{$id}" name="{key('name', concat('name-', $id + 27))/sse:string/@value}"
                            price="{$prices[$id + 28]/sse:integer/@value}" critical="{$id}">
                        <xsl:apply-templates select="sse:integer[@name='hit' or @name='damage' or @name='spell']" />
                        <xsl:call-template name="equipment-classes">
                            <xsl:with-param name="mask" select="$file/sse:instruction[@name='weapon-permissions']/sse:group[$id]/sse:integer/@value" />
                        </xsl:call-template>
                    </weapon>
                </xsl:for-each>
            </weapons>
            <armor>
                <xsl:for-each select="$file/sse:instruction[@name='armor']/sse:group">
                    <xsl:variable name="id" select="position()" />
                    <item id="{$id}" name="{key('name', concat('name-', $id + 67))/sse:string/@value}"
                          price="{$prices[$id + 68]/sse:integer/@value}"
                          category="{$file/sse:instruction[@name='armor-categories']/sse:group[$id]/sse:integer/@value}">
                        <xsl:apply-templates select="sse:integer" />
                        <xsl:call-template name="equipment-classes">
                            <xsl:with-param name="mask" select="$file/sse:instruction[@name='armor-permissions']/sse:group[$id]/sse:integer/@value" />
                        </xsl:call-template>
                    </item>
                </xsl:for-each>
            </armor>
            <spells>
                <xsl:for-each select="$file/sse:instruction[@name='spells']/sse:group">
                    <xsl:variable name="id" select="position()" />
                    <xsl:variable name="level" select="floor(($id - 1) div 8) + 1" />
                    <spell id="{$id}" name="{key('name', concat('name-', $id + 175))/sse:string/@value}"
                           price="{$prices[$id + 176]/sse:integer/@value}" level="{$level}">
                        <xsl:attribute name="school">
                            <xsl:choose>
                                <xsl:when test="($id - 1) mod 8 &lt; 4">white</xsl:when>
                                <xsl:otherwise>black</xsl:otherwise>
                            </xsl:choose>
                        </xsl:attribute>
                        <xsl:apply-templates select="sse:integer" />
                        <xsl:variable name="bit">
                            <xsl:call-template name="power-of-two">
                                <xsl:with-param name="exponent" select="7 - (($id - 1) mod 8)" />
                            </xsl:call-template>
                        </xsl:variable>
                        <xsl:for-each select="$file/sse:group[@name='spell-permissions']/sse:group">
                            <xsl:if test="floor(sse:instruction[@name='levels']/sse:group[$level]/sse:integer/@value div number($bit)) mod 2 = 0">
                                <class-ref id="{substring-after(@name, 'class-')}" />
                            </xsl:if>
                        </xsl:for-each>
                    </spell>
                </xsl:for-each>
            </spells>
        </final-fantasy>
    </xsl:template>
    <xsl:template match="sse:integer">
        <xsl:attribute name="{@name}">
            <xsl:value-of select="@value" />
        </xsl:attribute>
    </xsl:template>
    <xsl:template name="equipment-classes">
        <xsl:param name="mask" />
        <xsl:param name="class" select="0" />
        <xsl:param name="bit" select="2048" />
        <xsl:if test="floor($mask div $bit) mod 2 = 0">
            <class-ref id="{$class}" />
        </xsl:if>
        <xsl:if test="$class &lt; 11">
            <xsl:call-template name="equipment-classes">
                <xsl:with-param name="mask" select="$mask" />
                <xsl:with-param name="class" select="$class + 1" />
                <xsl:with-param name="bit" select="$bit div 2" />
            </xsl:call-template>
        </xsl:if>
    </xsl:template>
    <xsl:template name="power-of-two">
        <xsl:param name="exponent" />
        <xsl:param name="value" select="1" />
        <xsl:choose>
            <xsl:when test="$exponent = 0">
                <xsl:value-of select="$value" />
            </xsl:when>
            <xsl:otherwise>
                <xsl:call-template name="power-of-two">
                    <xsl:with-param name="exponent" select="$exponent - 1" />
                    <xsl:with-param name="value" select="$value * 2" />
                </xsl:call-template>
            </xsl:otherwise>
        </xsl:choose>
    </xsl:template>
</xsl:stylesheet>
