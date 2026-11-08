<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:sse="http://schema.slothsoft.net/savegame/editor"
                xmlns:sf1="http://schema.slothsoft.net/final-fantasy/i" exclude-result-prefixes="sse">
    <xsl:output method="xml" encoding="UTF-8" indent="yes" />
    <xsl:param name="rom-sha256" />
    <xsl:variable name="file" select="/sse:savegame/sse:archive/sse:file" />
    <xsl:variable name="prices" select="$file/sse:instruction[@name='prices']/sse:group" />
    <xsl:template match="/">
        <sf1:final-fantasy version="1.0" region="US" rom-sha256="{$rom-sha256}">
            <sf1:classes>
                <xsl:for-each select="$file/sse:instruction[@name='class-names']/sse:group">
                    <sf1:class id="{position() - 1}" name="{sse:string/@value}" />
                </xsl:for-each>
            </sf1:classes>
            <sf1:weapons>
                <xsl:for-each select="$file/sse:instruction[@name='weapons']/sse:group">
                    <xsl:variable name="id" select="position()" />
                    <!-- The original US battle code uses the weapon ID as its critical threshold.
                         Its stored critical, elemental and category fields are not applied. -->
                    <sf1:weapon id="{$id}" name="{$file/sse:instruction[@name='weapon-names']/sse:group[$id]/sse:string/@value}"
                                price="{$prices[$id + 28]/sse:integer/@value}" critical="{$id}">
                        <xsl:apply-templates select="sse:integer[@name='hit' or @name='damage' or @name='spell']" />
                        <xsl:copy-of select="$file/sse:instruction[@name='weapon-names']/sse:group[$id]/sse:string/sf1:label" />
                        <xsl:call-template name="equipment-classes">
                            <xsl:with-param name="mask" select="$file/sse:instruction[@name='weapon-permissions']/sse:group[$id]/sse:integer/@value" />
                        </xsl:call-template>
                    </sf1:weapon>
                </xsl:for-each>
            </sf1:weapons>
            <sf1:armor>
                <xsl:for-each select="$file/sse:instruction[@name='armor']/sse:group">
                    <xsl:variable name="id" select="position()" />
                    <sf1:item id="{$id}" name="{$file/sse:instruction[@name='armor-names']/sse:group[$id]/sse:string/@value}"
                              price="{$prices[$id + 68]/sse:integer/@value}"
                              category="{$file/sse:instruction[@name='armor-categories']/sse:group[$id]/sse:integer/@value}">
                        <xsl:apply-templates select="sse:integer" />
                        <xsl:copy-of select="$file/sse:instruction[@name='armor-names']/sse:group[$id]/sse:string/sf1:label" />
                        <xsl:call-template name="equipment-classes">
                            <xsl:with-param name="mask" select="$file/sse:instruction[@name='armor-permissions']/sse:group[$id]/sse:integer/@value" />
                        </xsl:call-template>
                    </sf1:item>
                </xsl:for-each>
            </sf1:armor>
            <sf1:spells>
                <xsl:for-each select="$file/sse:instruction[@name='spells']/sse:group">
                    <xsl:variable name="id" select="position()" />
                    <xsl:variable name="level" select="floor(($id - 1) div 8) + 1" />
                    <sf1:spell id="{$id}" name="{$file/sse:instruction[@name='spell-names']/sse:group[$id]/sse:string/@value}"
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
                        <xsl:for-each select="$file/sse:instruction[@name='spell-permissions']/sse:group">
                            <xsl:if test="floor(sse:instruction[@name='levels']/sse:group[$level]/sse:integer/@value div number($bit)) mod 2 = 0">
                                <sf1:class-ref id="{position() - 1}" />
                            </xsl:if>
                        </xsl:for-each>
                    </sf1:spell>
                </xsl:for-each>
            </sf1:spells>
            <sf1:spell-rules
                base-hit="{$file/sse:integer[@name='magic-base-hit-low']/@value + 256 * $file/sse:integer[@name='magic-base-hit-high']/@value}"
                roll-max="{$file/sse:integer[@name='magic-roll-max']/@value}"
                weakness-hit="{$file/sse:integer[@name='magic-weakness-hit']/@value}"
                life-hp="{$file/sse:integer[@name='life-hp']/@value}"
                hp-limit="{$file/sse:integer[@name='magic-hp-limit-low']/@value + 256 * $file/sse:integer[@name='magic-hp-limit-high']/@value}">
                <xsl:for-each select="$file/sse:instruction[@name='menu-cure']/sse:group">
                    <sf1:field-healing spell="{1 + (position() - 1) * 16}">
                        <xsl:apply-templates select="sse:integer" />
                    </sf1:field-healing>
                </xsl:for-each>
                <xsl:for-each select="$file/sse:instruction[@name='menu-heal']/sse:group">
                    <sf1:field-healing spell="{20 + (position() - 1) * 16}">
                        <xsl:apply-templates select="sse:integer" />
                    </sf1:field-healing>
                </xsl:for-each>
            </sf1:spell-rules>
        </sf1:final-fantasy>
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
            <sf1:class-ref id="{$class}" />
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
